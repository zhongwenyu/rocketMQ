<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\consumer\store\ReadOffsetType;
use ybrenLib\rocketmq\core\ConcurrentMap;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\MQAsyncClientInstance;
use ybrenLib\rocketmq\MQClientApi;
use ybrenLib\rocketmq\MQConstants;
use ybrenLib\rocketmq\remoting\body\LockBatchRequestBody;
use ybrenLib\rocketmq\remoting\body\UnlockBatchRequestBody;
use ybrenLib\rocketmq\remoting\heartbeat\ConsumeType;
use ybrenLib\rocketmq\remoting\heartbeat\SubscriptionData;
use ybrenLib\rocketmq\util\ArrayUtil;
use ybrenLib\rocketmq\util\MixUtil;
use ybrenLib\rocketmq\util\ScheduleTaskUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class RebalanceImpl
{
    /**
     * @var Logger
     */
    private $log;
    /**
     * 移除processQueue时，若processQueue在消费中，等待再次移除的时间
     * @var int
     */
    private static $UNLOCK_DELAY_TIME_MILLS = 20000;

    /**
     * @var SubscriptionData[]
     */
    protected $subscriptionInner = [];
    /**
     * @var ProcessQueue[]
     */
    protected $processQueueTable;
    /**
     * @var array
     */
    protected $topicSubscribeInfoTable = [];
    protected $consumerGroup;
    /**
     * @var MessageModel
     */
    protected $messageModel;
    /**
     * @var AllocateMessageQueueStrategy
     */
    protected $allocateMessageQueueStrategy;
    /**
     * @var MQAsyncClientInstance
     */
    protected $mQClientFactory;
    /**
     * @var DefaultMQConsumer
     */
    protected $defaultMQConsumer;

    /**
     * RebalanceImpl constructor.
     * @param DefaultMQConsumer $defaultMQConsumer
     */
    public function __construct(DefaultMQConsumer $defaultMQConsumer)
    {
        $this->defaultMQConsumer = $defaultMQConsumer;
        $this->log = LoggerFactory::getLogger(RebalanceImpl::class);
        $this->processQueueTable = new ConcurrentMap();
    }

    public function doRebalance(bool $isOrder) {
        if (!empty($this->subscriptionInner)) {
            foreach ($this->subscriptionInner as $topic => $subscriptionData) {
                try {
                    $this->rebalanceByTopic($topic, $isOrder);
                } catch (\Exception $e) {
                    if (strpos(MixUtil::$RETRY_GROUP_TOPIC_PREFIX , $topic) === false) {
                        $this->log->warn("rebalanceByTopic Exception". " error: ".$e->getMessage());
                        $this->log->warn($e->getTraceAsString());
                    }
                }
            }
        }

        $this->truncateMessageQueueNotMyTopic();
    }

    public function rebalanceByTopic($topic , bool $isOrder){
        switch ($this->messageModel) {
            case MessageModel::BROADCASTING: {
                /*$mqSet = $this->topicSubscribeInfoTable[$topic];
                if (!empty($mqSet)) {
                    $changed = $this->updateProcessQueueTableInRebalance(topic, mqSet, isOrder);
                    if (changed) {
                        this.messageQueueChanged(topic, mqSet, mqSet);
                        $this->log->info("messageQueueChanged {} {} {} {}",
                            consumerGroup,
                            topic,
                            mqSet,
                            mqSet);
                    }
                } else {
                    $this->log->warn("doRebalance, {}, but the topic[{}] not exist.", consumerGroup, topic);
                }*/
                break;
            }
            case MessageModel::CLUSTERING: {
                $mqSet = $this->topicSubscribeInfoTable[$topic] ?? null;
                $cidAll = $this->mQClientFactory->findConsumerIdList($topic, $this->consumerGroup);
                if (null == $mqSet) {
                    if (strpos($topic , MixUtil::$RETRY_GROUP_TOPIC_PREFIX) != 0) {
                        $this->log->warn("doRebalance, {}, but the topic[{}] not exist.", $this->consumerGroup, $topic);
                    }
                }

                if (null == $cidAll) {
                    $this->log->warn("doRebalance, {} {}, get consumer id list failed", $this->consumerGroup, $topic);
                }

                if ($mqSet != null && $cidAll != null) {
                    $mqAll = $mqSet;
                    asort($mqAll);
                    asort($cidAll);

                    $strategy = $this->allocateMessageQueueStrategy;

                    $allocateResult = null;
                    try {
                        $allocateResult = $strategy->allocate(
                                $this->consumerGroup,
                                $this->mQClientFactory->getClientId(),
                                $mqAll,
                                $cidAll);
                    } catch (\Exception $e) {
                        $this->log->error("AllocateMessageQueueStrategy.allocate Exception. allocateMessageQueueStrategyName={}", $strategy->getName() ." error: " . $e->getMessage());
                        return;
                    }

                    $allocateResultSet = [];
                    if (!empty($allocateResult)) {
                        $allocateResultSet = $allocateResult;
                    }

                    $changed = $this->updateProcessQueueTableInRebalance($topic, $allocateResultSet, $isOrder);
                    /*if (changed) {
                        $this->log->info(
                            "rebalanced result changed. allocateMessageQueueStrategyName={}, group={}, topic={}, clientId={}, mqAllSize={}, cidAllSize={}, rebalanceResultSize={}, rebalanceResultSet={}",
                            strategy.getName(), consumerGroup, topic, $this->mQClientFactory->getClientId(), mqSet.size(), cidAll.size(),
                            allocateResultSet.size(), allocateResultSet);
                        this.messageQueueChanged(topic, mqSet, allocateResultSet);
                    }*/
                }
                break;
            }
            default:
                break;
        }
    }

    private function removeUnnecessaryMessageQueue(MessageQueue $mq , ProcessQueue $pq){
        $this->defaultMQConsumer->getOffsetStore()->persist($mq);
        $this->defaultMQConsumer->getOffsetStore()->removeOffset($mq);
        if ($this->defaultMQConsumer->isConsumeOrderly()
            && MessageModel::CLUSTERING == $this->defaultMQConsumer->messageModel()) {
            try {
                $lockResult = $pq->getLockConsume()->tryLock(1);
                if ($lockResult) {
                    try {
                        return $this->unlockDelay($mq, $pq);
                    } finally {
                        $pq->getLockConsume()->release();
                    }
                } else {
                    $this->log->warn("[WRONG]mq is consuming, so can not unlock it, {}",
                        json_encode($mq));

                //    $pq->incTryUnlockTimes();
                }
            } catch (\Exception $e) {
                $this->log->error("removeUnnecessaryMessageQueue Exception: {}", $e->getMessage());
            }

            return false;
        }
        return true;
    }

    private function unlockDelay(MessageQueue $mq, ProcessQueue $pq) {
        if ($pq->hasTempMessage()) {
            $this->log->info("[{}]unlockDelay, begin {} ", $mq->hashCode(), json_encode($mq));
            ScheduleTaskUtil::after(self::$UNLOCK_DELAY_TIME_MILLS, function () use($mq) {
                $this->log->info("[{}]unlockDelay, execute at once {}", $mq->hashCode(), json_encode($mq));
                $this->unlock($mq, true);
            });
        }else{
            $this->unlock($mq, true);
        }
        return true;
    }

    private function updateProcessQueueTableInRebalance(string $topic, array $mqSet,bool $isOrder) {
        $changed = false;

        foreach ($this->processQueueTable as $mq => $pq) {
            if ($mq->getTopic() == $topic) {
                if (!ArrayUtil::inArray($mq , $mqSet)) {
                    $pq->setDropped(true);
                    if ($this->removeUnnecessaryMessageQueue($mq, $pq)) {
                        $this->processQueueTable->remove($mq);
                        $changed = true;
                        $this->log->info("doRebalance, {}, remove unnecessary mq, {}", $this->consumerGroup, json_encode($mq));
                    }
                } else if ($pq->isPullExpired()) {
                    switch ($this->consumeType()) {
                        case ConsumeType::CONSUME_ACTIVELY:
                            break;
                        case ConsumeType::CONSUME_PASSIVELY:
                            $pq->setDropped(true);
                            if ($this->removeUnnecessaryMessageQueue($mq, $pq)) {
                                $this->processQueueTable->remove($mq);
                                $changed = true;
                                $this->log->error("[BUG]doRebalance, {}, remove unnecessary mq, {}, because pull is pause, so try to fixed it",
                                    $this->consumerGroup,json_encode($mq));
                            }
                            break;
                        default:
                            break;
                    }
                }
            }
        }

        $pullRequestList = [];
        foreach ($mqSet as $mq) {
            // 新增队列
            if (!$this->processQueueTable->containsKey($mq)) {
                if ($isOrder && !$this->tryLock($mq)) {
                    $this->log->warn("doRebalance, {}, add a new mq failed, {}, because lock failed", $this->consumerGroup, json_encode($mq));
                    continue;
                }

                $this->removeDirtyOffset($mq);
                $pq = new ProcessQueue();
                $nextOffset = $this->computePullFromWhere($mq);
                if ($nextOffset >= 0) {
                    $pre = $this->processQueueTable->putIfAbsent($mq, $pq);
                    if ($pre != null) {
                        $this->log->info("doRebalance, {}, mq already exists, {}", $this->consumerGroup, json_encode($mq));
                    } else {
                        $this->log->info("doRebalance, {}, add a new mq, {}", $this->consumerGroup, json_encode($mq));
                        $pullRequest = new PullRequest();
                        $pullRequest->setConsumerGroup($this->consumerGroup);
                        $pullRequest->setNextOffset($nextOffset);
                        $pullRequest->setMessageQueue($mq);
                        $pullRequest->setProcessQueue($pq);
                        $pullRequestList[] = $pullRequest;
                        $changed = true;
                    }
                } else {
                    $this->log->warn("doRebalance, {}, add new mq failed, {}", $this->consumerGroup, json_encode($mq));
                }
            }
        }

        $this->dispatchPullRequest($pullRequestList);

        return $changed;
    }

    /**
     * 顺序消费时请求broker给队列加锁
     * @param MessageQueue $mq
     * @return bool
     */
    public function lock(MessageQueue $mq) {
        $findBrokerResult = $this->mQClientFactory->findBrokerAddressInSubscribe($mq->getBrokerName(), MQConstants::MASTER_ID, true);
        if ($findBrokerResult != null) {
            $requestBody = new LockBatchRequestBody();
            $requestBody->setConsumerGroup($this->consumerGroup);
            $requestBody->setClientId($this->mQClientFactory->getClientId());
            $requestBody->addMq($mq);

            try {
                $lockedMq = MQClientApi::lockBatchMQ($this->mQClientFactory->getOrCreateSyncClient($findBrokerResult->getBrokerAddr()) , $requestBody);
                foreach ($lockedMq as $mmqq) {
                    $processQueue = $this->processQueueTable->get($mmqq);
                    if ($processQueue != null) {
                        $processQueue->setLocked(true);
                        $processQueue->setLastLockTimestamp(TimeUtil::currentTimeMillis());
                    }
                }

                $lockOK = ArrayUtil::inArray($mq , $lockedMq);
                $this->log->info("the message queue lock {}, {} {}",
                    $lockOK ? "OK" : "Failed",
                    $this->consumerGroup,
                    json_encode($lockOK));
                return $lockOK;
            } catch (\Exception $e) {
                $this->log->error("lockBatchMQ exception, " . json_encode($mq) . " error: " . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * 向broker发送解除队列锁的请求
     * @param MessageQueue $mq
     * @param bool $oneway
     */
    public function unlock(MessageQueue $mq, bool $oneway) {
        $findBrokerResult = $this->mQClientFactory->findBrokerAddressInSubscribe($mq->getBrokerName(), MQConstants::MASTER_ID, true);
        if ($findBrokerResult != null) {
            $requestBody = new UnlockBatchRequestBody();
            $requestBody->setConsumerGroup($this->consumerGroup);
            $requestBody->setClientId($this->mQClientFactory->getClientId());
            $requestBody->addMq($mq);

            try {
                MQClientApi::unlockBatchMQ($this->mQClientFactory->getOrCreateSyncClient($findBrokerResult->getBrokerAddr()) , $requestBody);
                $this->log->warn("unlock messageQueue. group:{}, clientId:{}, mq:{}",
                    $this->consumerGroup,
                    $this->mQClientFactory->getClientId(),
                    json_encode($mq));
            } catch (\Exception $e) {
                $this->log->error("unlockBatchMQ exception, " . json_encode($mq) . " error: " . $e->getMessage());
            }
        }
    }

    public function tryLock(MessageQueue $mq){
        return true;
    }

    public function consumeType() {
        return ConsumeType::CONSUME_PASSIVELY;
    }

    public function removeDirtyOffset(MessageQueue $mq) {
        $this->defaultMQConsumer->getOffsetStore()->removeOffset($mq);
    }

    public function computePullFromWhere(MessageQueue $mq) {
        $result = -1;
        $consumeFromWhere = $this->defaultMQConsumer->getConsumeFromWhere();
        $offsetStore = $this->defaultMQConsumer->getOffsetStore();
        switch ($consumeFromWhere) {
            case ConsumeFromWhere::CONSUME_FROM_LAST_OFFSET_AND_FROM_MIN_WHEN_BOOT_FIRST:
            case ConsumeFromWhere::CONSUME_FROM_MIN_OFFSET:
            case ConsumeFromWhere::CONSUME_FROM_MAX_OFFSET:
            case ConsumeFromWhere::CONSUME_FROM_LAST_OFFSET: {
                $lastOffset = $offsetStore->readOffset($mq, ReadOffsetType::READ_FROM_STORE);
                $this->log->info("queue ".json_encode($mq)." readOffset: ".$lastOffset);
                if ($lastOffset >= 0) {
                    $result = $lastOffset;
                }
                // First start,no offset
                else if (-1 == $lastOffset) {
                    if (strpos($mq->getTopic() , MixUtil::$RETRY_GROUP_TOPIC_PREFIX) == 0) {
                        $result = 0;
                    } else {
                        try {
                            $result = $this->mQClientFactory->maxOffset($mq);
                        } catch (\Exception $e) {
                            $this->log->error("get queue ".json_encode($mq)." maxOffset error: ".$e->getMessage());
                            $result = -1;
                        }
                    }
                } else {
                    $result = -1;
                }
                break;
            }
            case ConsumeFromWhere::CONSUME_FROM_FIRST_OFFSET: {
                $lastOffset = $offsetStore->readOffset($mq, ReadOffsetType::READ_FROM_STORE);
                if ($lastOffset >= 0) {
                    $result = $lastOffset;
                } else if (-1 == $lastOffset) {
                    $result = 0;
                } else {
                    $result = -1;
                }
                break;
            }
            case ConsumeFromWhere::CONSUME_FROM_TIMESTAMP:
            default:
                break;
        }

        return $result;
    }

    private function truncateMessageQueueNotMyTopic() {
        $messageQueues = $this->processQueueTable->keys();
        foreach ($messageQueues as $messageQueue) {
            $topic = $messageQueue->getTopic();
            if (!isset($this->subscriptionInner[$topic])) {
                $pq = $this->processQueueTable->remove($messageQueue);
                if ($pq != null) {
                    $pq->setDropped(true);
                    $this->log->info("doRebalance, {}, truncateMessageQueueNotMyTopic remove unnecessary mq, {}", $this->consumerGroup, json_encode($messageQueue));
                }
            }
        }
    }

    public function dispatchPullRequest(array $pullRequestList){
        foreach ($pullRequestList as $pullRequest){
            $this->defaultMQConsumer->executePullRequestImmediately($pullRequest);
        }
    }

    public function removeProcessQueue(MessageQueue $mq) {
        $prev = $this->processQueueTable->remove($mq);
        if ($prev != null) {
            $droped = $prev->isDropped();
            $prev->setDropped(true);
            $this->removeUnnecessaryMessageQueue($mq, $prev);
            $this->log->info("Fix Offset, {}, remove unnecessary mq, {} Droped: {}", $this->consumerGroup, json_encode($mq), $droped);
        }
    }

    /**
     * 关机
     */
    public function shutdown(){
        if($this->processQueueTable->size() > 0){
            foreach ($this->processQueueTable as $mq => $pq){
                $pq->setDropped(true);
            }
        }
    }

    /**
     * @return ProcessQueue[]|ConcurrentMap
     */
    public function getProcessQueueTable(){
        return $this->processQueueTable;
    }

    /**
     * @param $topic
     * @param SubscriptionData $subscriptionData
     */
    public function addSubscriptionData($topic , SubscriptionData $subscriptionData){
        $this->subscriptionInner[$topic] = $subscriptionData;
    }

    /**
     * @param $topic
     * @param MessageQueue[] $info
     */
    public function addTopicSubscribeInfo($topic , $info){
        $this->topicSubscribeInfoTable[$topic] = $info;
    }

    public function getTopicSubscribeInfoTable(){
        return $this->topicSubscribeInfoTable;
    }

    /**
     * @return mixed
     */
    public function getConsumerGroup()
    {
        return $this->consumerGroup;
    }

    /**
     * @param mixed $consumerGroup
     */
    public function setConsumerGroup($consumerGroup)
    {
        $this->consumerGroup = $consumerGroup;
    }

    /**
     * @return MessageModel
     */
    public function getMessageModel(): MessageModel
    {
        return $this->messageModel;
    }

    /**
     * @param $messageModel
     */
    public function setMessageModel($messageModel)
    {
        $this->messageModel = $messageModel;
    }

    /**
     * @return AllocateMessageQueueStrategy
     */
    public function getAllocateMessageQueueStrategy(): AllocateMessageQueueStrategy
    {
        return $this->allocateMessageQueueStrategy;
    }

    /**
     * @param AllocateMessageQueueStrategy $allocateMessageQueueStrategy
     */
    public function setAllocateMessageQueueStrategy(AllocateMessageQueueStrategy $allocateMessageQueueStrategy)
    {
        $this->allocateMessageQueueStrategy = $allocateMessageQueueStrategy;
    }

    /**
     * @return MQAsyncClientInstance
     */
    public function getMQClientFactory(): MQAsyncClientInstance
    {
        return $this->mQClientFactory;
    }

    /**
     * @param MQAsyncClientInstance $mQClientFactory
     */
    public function setMQClientFactory(MQAsyncClientInstance $mQClientFactory)
    {
        $this->mQClientFactory = $mQClientFactory;
    }

    /**
     * @return SubscriptionData[]
     */
    public function getSubscriptionInner(): array
    {
        return $this->subscriptionInner;
    }
}