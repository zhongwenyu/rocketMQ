<?php
namespace ybrenLib\rocketmq\consumer\store;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\core\AtomicLong;
use ybrenLib\rocketmq\core\ConcurrentMap;
use ybrenLib\rocketmq\core\ObjectMap;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\MQAsyncClientInstance;
use ybrenLib\rocketmq\MQClientApi;
use ybrenLib\rocketmq\remoting\header\broker\QueryConsumerOffsetRequestHeader;
use ybrenLib\rocketmq\remoting\header\broker\UpdateConsumerOffsetRequestHeader;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\util\ArrayUtil;
use ybrenLib\rocketmq\util\MixUtil;

class RemoteBrokerOffsetStore implements OffsetStore
{
    /**
     * @var Logger
     */
    private $log;

    /**
     * @var MQAsyncClientInstance
     */
    private $mqClientFactory;

    /**
     * @var string
     */
    private $groupName;

    /**
     * @var AtomicLong[]
     */
    private $offsetTable;

    /**
     * LocalFileOffsetStore constructor.
     * @param MQAsyncClientInstance $mqClientFactory
     * @param string $consumerGroup
     */
    public function __construct(MQAsyncClientInstance $mqClientFactory, string $consumerGroup)
    {
        $this->mqClientFactory = $mqClientFactory;
        $this->groupName = $consumerGroup;
        $this->offsetTable = new ConcurrentMap();
        $this->log = LoggerFactory::getLogger(RemoteBrokerOffsetStore::class);
    }

    function load()
    {
        // TODO: Implement load() method.
    }

    function updateOffset(MessageQueue $mq, $offset, bool $increaseOnly){
        if ($mq != null) {
            $offsetOld = $this->offsetTable->get($mq);
            if (null == $offsetOld) {
                $offsetOld = $this->offsetTable->putIfAbsent($mq, new AtomicLong($offset));
            }

            if (null != $offsetOld) {
                if ($increaseOnly) {
                    MixUtil::compareAndIncreaseOnly($offsetOld, $offset);
                } else {
                    $offsetOld->set($offset);
                }
            }
        }
    }

    function readOffset(MessageQueue $mq, $type)
    {
        if ($mq != null) {
            switch ($type) {
                case ReadOffsetType::MEMORY_FIRST_THEN_STORE:
                case ReadOffsetType::READ_FROM_MEMORY: {
                    $offset = $this->offsetTable->get($mq);
                 //   // var_dump("read offset: " . $offset->get());
                    if ($offset != null) {
                        return $offset->get();
                    } else if (ReadOffsetType::READ_FROM_MEMORY == $type) {
                        return -1;
                    }
                }
                case ReadOffsetType::READ_FROM_STORE: {
                    try {
                        $brokerOffset = $this->fetchConsumeOffsetFromBroker($mq);
                        // var_dump("fetchConsumeOffsetFromBroker: ".$brokerOffset);
                        $offset = new AtomicLong($brokerOffset);
                        $this->updateOffset($mq, $offset->get(), false);
                        return $brokerOffset;
                    }
                        // No offset in broker
                    catch (RocketMQClientException $e) {
                        return -1;
                    }
                        //Other exceptions
                    catch (\Exception $e) {
                        $this->log->error("queue {} readOffset from store error: {}" , json_encode($mq) , $e->getMessage());
                        return -2;
                    }
                }
                default:
                    break;
            }
        }

        return -1;
    }

    /**
     * @param MessageQueue[] $mqs
     * @return mixed|void
     */
    function persistAll($mqs)
    {
        // var_dump("start to persistAll: ".json_encode($mqs));
        if (empty($mqs))
            return;

        $unusedMQ = [];

        foreach ($this->offsetTable as $mq => $offset) {
            if ($offset != null) {
                if (ArrayUtil::inArray($mq , $mqs)) {
                    try {
                        $this->updateConsumeOffsetToBroker($mq, $offset->get() , true);
                        $this->log->info("[persistAll] Group: {} ClientId: {} updateConsumeOffsetToBroker {} {}",
                            $this->groupName,
                            $this->mqClientFactory->getClientId(),
                            json_encode($mq),
                            $offset->get());
                    } catch (\Exception $e) {
                        // var_dump("persistAll one error: ".$e->getMessage());
                        $this->log->error("updateConsumeOffsetToBroker exception, " . json_encode($mq). " error: ".$e->getMessage());
                    }
                } else {
                    $unusedMQ[] = $mq;
                }
            }
        }

        if (!empty($unusedMQ)) {
            foreach ($unusedMQ as $mq) {
                $this->offsetTable->remove($mq);
                $this->log->info("remove unused mq, {}, {}", json_encode($mq), $this->groupName);
            }
        }
    }

    function persist(MessageQueue $mq)
    {
        $offset = $this->offsetTable->get($mq);
        if ($offset != null) {
            try {
                $this->updateConsumeOffsetToBroker($mq, $offset->get() , true);
                $this->log->info("[persist] Group: {} ClientId: {} updateConsumeOffsetToBroker {} {}",
                    $this->groupName,
                    $this->mqClientFactory->getClientId(),
                    json_encode($mq),
                    $offset->get());
            } catch (\Exception $e) {
                $this->log->error("updateConsumeOffsetToBroker exception, " . json_encode($mq). " error: ".$e->getMessage());
            }
        }
    }

    function removeOffset(MessageQueue $mq)
    {
        if ($mq != null) {
            $this->offsetTable->remove($mq);
            $this->log->info("remove unnecessary messageQueue offset. group={}, mq={}, offsetTableSize={}", $this->groupName, json_encode($mq),
                $this->offsetTable->size());
        }
    }

    function cloneOffsetTable(string $topic)
    {
        $topic = trim($topic);
        $cloneOffsetTable = new ObjectMap();
        foreach ($this->offsetTable as $mq => $offset) {
            if (!empty($topic) && $topic != $mq->getTopic()) {
                continue;
            }
            $cloneOffsetTable->put($mq, $offset->get());
        }
        return $cloneOffsetTable;
    }

    /**
     * @param MessageQueue $mq
     * @return int
     */
    function fetchConsumeOffsetFromBroker(MessageQueue $mq){
        $findBrokerResult = $this->mqClientFactory->findBrokerAddressInAdmin($mq->getBrokerName());
        if (null == $findBrokerResult) {

            $this->mqClientFactory->updateTopicPublishInfoFromNamesrv($mq->getTopic());
            $findBrokerResult = $this->mqClientFactory->findBrokerAddressInAdmin($mq->getBrokerName());
        }

        if ($findBrokerResult != null) {
            $requestHeader = new QueryConsumerOffsetRequestHeader();
            $requestHeader->setTopic($mq->getTopic());
            $requestHeader->setConsumerGroup($this->groupName);
            $requestHeader->setQueueId($mq->getQueueId());
            $client = $this->mqClientFactory->getOrCreateSyncClient($findBrokerResult);
            return MQClientApi::queryConsumerOffset($client , $requestHeader);
        } else {
            throw new RocketMQClientException("The broker[" . $mq->getBrokerName() . "] not exist", null);
        }
    }

    function updateConsumeOffsetToBroker(MessageQueue $mq, int $offset, bool $isOneway)
    {
        $findBrokerResult = $this->mqClientFactory->findBrokerAddressInAdmin($mq->getBrokerName());
        if (null == $findBrokerResult) {
            $this->mqClientFactory->updateTopicPublishInfoFromNamesrv($mq->getTopic());
            $findBrokerResult = $this->mqClientFactory->findBrokerAddressInAdmin($mq->getBrokerName());
        }

        if ($findBrokerResult != null) {
            $requestHeader = new UpdateConsumerOffsetRequestHeader();
            $requestHeader->setTopic($mq->getTopic());
            $requestHeader->setConsumerGroup($this->groupName);
            $requestHeader->setQueueId($mq->getQueueId());
            $requestHeader->setCommitOffset($offset);

            $client = $this->mqClientFactory->getOrCreateAsyncClient($findBrokerResult);
            $request = RemotingCommand::createRequestCommand(RequestCode::$UPDATE_CONSUMER_OFFSET, $requestHeader);
            $response = $client->send($request);
        } else {
            throw new RocketMQClientException("The broker[" . $mq->getBrokerName() . "] not exist", null);
        }
    }

}