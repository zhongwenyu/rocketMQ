<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\core\AtomicLong;
use ybrenLib\rocketmq\core\ConcurrentMap;
use ybrenLib\rocketmq\core\ObjectMap;
use ybrenLib\rocketmq\core\ResponseFuture;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\entity\PullStatus;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\MQAsyncClientInstance;
use ybrenLib\rocketmq\MQClientApi;
use ybrenLib\rocketmq\MQConstants;
use ybrenLib\rocketmq\remoting\ByteBuf;
use ybrenLib\rocketmq\remoting\callback\InvokeCallback;
use ybrenLib\rocketmq\remoting\header\broker\PullMessageRequestHeader;
use ybrenLib\rocketmq\remoting\heartbeat\SubscriptionData;
use ybrenLib\rocketmq\remoting\MessageDecoder;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\remoting\ResponseCode;
use ybrenLib\rocketmq\util\StringUtil;

class PullAPIWrapper
{
    /**
     * @var MQAsyncClientInstance
     */
    private $mQClientFactory;
    private $consumerGroup;
    private $unitMode;
    private $connectBrokerByUser = false;
    private $defaultBrokerId = MQConstants::MASTER_ID;
    private $pullFromWhichNodeTable;

    /**
     * PullAPIWrapper constructor.
     * @param MQAsyncClientInstance $mQClientFactory
     * @param $consumerGroup
     * @param $unitMode
     */
    public function __construct($mQClientFactory, $consumerGroup, $unitMode){
        $this->mQClientFactory = $mQClientFactory;
        $this->consumerGroup = $consumerGroup;
        $this->unitMode = $unitMode;
        $this->pullFromWhichNodeTable = new ObjectMap();
    }

    public function recalculatePullFromWhichNode(MessageQueue $mq) {
        if ($this->connectBrokerByUser) {
            return $this->defaultBrokerId;
        }

        // 获取建议使用的brokerId
        $suggest = $this->pullFromWhichNodeTable[$mq] ?? null;
        if ($suggest != null) {
            return $suggest->get();
        }

        // 返回主brokerId
        return MQConstants::MASTER_ID;
    }

    public function updatePullFromWhichNode(MessageQueue $mq, $brokerId) {
        $suggest = $this->pullFromWhichNodeTable->get($mq);
        if (null == $suggest) {
            $this->pullFromWhichNodeTable->put($mq, new AtomicLong($brokerId));
        } else {
            $suggest->set($brokerId);
        }
    }

    /**
     * @param MessageQueue $mq
     * @param $subExpression
     * @param $expressionType
     * @param $subVersion
     * @param $offset
     * @param $maxNums
     * @param $sysFlag
     * @param $commitOffset
     * @param $brokerSuspendMaxTimeMillis
     * @param InvokeCallback $invokeCallback
     * @return bool
     * @throws RocketMQClientException
     */
    public function pullKernelImpl(
        MessageQueue $mq,  // 拉取消息的队列
        $subExpression,  // 消息过滤表达式
        $expressionType,
        $subVersion,
        $offset,
        $maxNums,
        $sysFlag,
        $commitOffset,
        $brokerSuspendMaxTimeMillis,
        InvokeCallback $invokeCallback
    ){
        $findBrokerResult = $this->mQClientFactory->findBrokerAddressInSubscribe($mq->getBrokerName(),
            $this->recalculatePullFromWhichNode($mq), false);
        if (null == $findBrokerResult) {
            $this->mQClientFactory->updateTopicPublishInfoFromNamesrv($mq->getTopic());
            $findBrokerResult = $this->mQClientFactory->findBrokerAddressInSubscribe($mq->getBrokerName(),
                $this->recalculatePullFromWhichNode($mq), false);
        }
        // var_dump("findBrokerResult: ".json_encode($findBrokerResult));

        if ($findBrokerResult != null) {
            $sysFlagInner = $sysFlag;

            if ($findBrokerResult->isSlave()) {
                $sysFlagInner = PullSysFlag::clearCommitOffsetFlag($sysFlagInner);
            }

            $requestHeader = new PullMessageRequestHeader();
            $requestHeader->setConsumerGroup($this->consumerGroup);
            $requestHeader->setTopic($mq->getTopic());
            $requestHeader->setQueueId($mq->getQueueId());
            $requestHeader->setQueueOffset($offset);
            $requestHeader->setMaxMsgNums($maxNums);
            $requestHeader->setSysFlag($sysFlagInner);
            $requestHeader->setCommitOffset($commitOffset);
            $requestHeader->setSuspendTimeoutMillis($brokerSuspendMaxTimeMillis);
            $requestHeader->setSubscription($subExpression);
            $requestHeader->setSubVersion($subVersion);
            $requestHeader->setExpressionType($expressionType);

            $brokerAddr = $findBrokerResult->getBrokerAddr();
            /*if (PullSysFlag::hasClassFilterFlag($sysFlagInner)) {
                $brokerAddr = $this->computPullFromWhichFilterServer($mq->getTopic(), brokerAddr);
            }*/

            $pullResult = true;
            $client = $this->mQClientFactory->getOrCreateAsyncClient($brokerAddr);
            if($client == null){
                $pullResult = false;
            }
            $request = RemotingCommand::createRequestCommand(RequestCode::$PULL_MESSAGE, $requestHeader);
            // var_dump("pull message:" . json_encode($request));
            $sendResult = MQClientApi::invokeAsync($client , $request , $invokeCallback);
            if($sendResult === false){
                $pullResult = false;
            }
            return $pullResult;
        }

        throw new RocketMQClientException("The broker[" . $mq->getBrokerName() . "] not exist", null);

    }

    public function processPullResult(MessageQueue $mq, PullResultExt $pullResultExt,
                                      SubscriptionData $subscriptionData , RemotingCommand $remotingCommand) {
        $this->updatePullFromWhichNode($mq, $pullResultExt->getSuggestWhichBrokerId());
        if (PullStatus::FOUND == $pullResultExt->getPullStatus()) {
            $byteBuffer = new ByteBuf($pullResultExt->getMessageBinary());
            $msgList = MessageDecoder::decodeMessages($byteBuffer , $remotingCommand);
            $msgListFilterAgain = $msgList;
            $tagsSet = $subscriptionData->getTagsSet();
            if (!empty($tagsSet) && !$subscriptionData->isClassFilterMode()) {
                $msgListFilterAgain = [];
                foreach ($msgList as $msg) {
                    if ($msg->getTags() != null) {
                        if (in_array($msg->getTags() , $tagsSet)) {
                            $msgListFilterAgain[] = $msg;
                        }
                    }
                }
            }

            /*if ($this->hasHook()) {
                FilterMessageContext filterMessageContext = new FilterMessageContext();
                            filterMessageContext.setUnitMode(unitMode);
                            filterMessageContext.setMsgList(msgListFilterAgain);
                            $this->executeHook(filterMessageContext);
                        }*/

            foreach ($msgListFilterAgain as $msg) {
                $traFlag = $msg->getProperty(MessageConst::PROPERTY_TRANSACTION_PREPARED);
                $msg->setTopic($mq->getTopic());
                if (StringUtil::toBool($traFlag)) {
                    $msg->setTransactionId($msg->getProperty(MessageConst::PROPERTY_UNIQ_CLIENT_MESSAGE_ID_KEYIDX));
                }
                $msg->putProperty(MessageConst::PROPERTY_MIN_OFFSET , $pullResultExt->getMinOffset());
                $msg->putProperty(MessageConst::PROPERTY_MAX_OFFSET , $pullResultExt->getMaxOffset());
                $msg->setBrokerName($mq->getBrokerName());
            }

            $pullResultExt->setMsgFoundList($msgListFilterAgain);
        }

        $pullResultExt->setMessageBinary(null);

        return $pullResultExt;
    }

    public function processPullResponse(RemotingCommand $remotingCommand){
        switch ($remotingCommand->getCode()) {
            case ResponseCode::$SUCCESS:
                $pullStatus = PullStatus::FOUND;
                break;
            case ResponseCode::$PULL_NOT_FOUND:
                $pullStatus = PullStatus::NO_NEW_MSG;
                break;
            case ResponseCode::$PULL_RETRY_IMMEDIATELY:
                $pullStatus = PullStatus::NO_MATCHED_MSG;
                break;
            case ResponseCode::$PULL_OFFSET_MOVED:
                $pullStatus = PullStatus::OFFSET_ILLEGAL;
                break;
            default:
                throw new RocketMQClientException($remotingCommand->getCode(), $remotingCommand->getRemark());
        }

        $responseHeader = $remotingCommand->getExtFields();
        return new PullResultExt($pullStatus, $responseHeader['nextBeginOffset'] , $responseHeader['minOffset'] ,
            $responseHeader['maxOffset'] , null, $responseHeader['suggestWhichBrokerId'] , $remotingCommand->getBody());
    }
}