<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\consumer\rebalance\AllocateMessageQueueAveragely;
use ybrenLib\rocketmq\consumer\store\LocalFileOffsetStore;
use ybrenLib\rocketmq\consumer\store\OffsetStore;
use ybrenLib\rocketmq\consumer\store\ReadOffsetType;
use ybrenLib\rocketmq\consumer\store\RemoteBrokerOffsetStore;
use ybrenLib\rocketmq\entity\Message;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\entity\PullStatus;
use ybrenLib\rocketmq\entity\ServiceState;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\MQAsyncClientInstance;
use ybrenLib\rocketmq\MQClientInstanceFactory;
use ybrenLib\rocketmq\remoting\header\broker\ConsumerSendMsgBackRequestHeader;
use ybrenLib\rocketmq\remoting\heartbeat\ConsumeType;
use ybrenLib\rocketmq\remoting\heartbeat\SubscriptionData;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\remoting\ResponseCode;
use ybrenLib\rocketmq\util\FilterApi;
use ybrenLib\rocketmq\util\IntegerUtil;
use ybrenLib\rocketmq\util\MixUtil;
use ybrenLib\rocketmq\util\ScheduleTaskUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class DefaultMQConsumer implements MQConsumerInner
{
    /**
     * @var Logger
     */
    private $log;
    /**
     * Maximum time to await message consuming when shutdown consumer, 0 indicates no await.
     */
    private $awaitTerminationMillisWhenShutdown = 30*1000;
    /**
     * 消息拉取失败时重试等待时间
     */
    private $pullTimeDelayMillsWhenException = 3000;
    /**
     * Threshold for dynamic adjustment of the number of thread pool
     */
    private $adjustThreadPoolNumsThreshold = 100000;

    /**
     * Concurrently max span offset.it has no effect on sequential consumption
     */
    private $consumeConcurrentlyMaxSpan = 2000;

    /**
     * Flow control threshold on queue level, each message queue will cache at most 1000 messages by default,
     * Consider the {@code pullBatchSize}, the instantaneous value may exceed the limit
     */
    private $pullThresholdForQueue = 1000;

    /**
     * Max re-consume times. -1 means 16 times.
     * @var int
     */
    private $maxReconsumeTimes = -1;

    /**
     * Limit the cached message size on queue level, each message queue will cache at most 100 MiB messages by default,
     * Consider the {@code pullBatchSize}, the instantaneous value may exceed the limit
     *
     * <p>
     * The size of a message only measured by message body, so it's not accurate
     */
    private $pullThresholdSizeForQueue = 100;
    private $queueFlowControlTimes = 0;
    private $queueMaxSpanFlowControlTimes = 0;
    private $PULL_TIME_DELAY_MILLS_WHEN_FLOW_CONTROL = 50;
    private $PULL_TIME_DELAY_MILLS_WHEN_SUSPEND = 1000;
    private $BROKER_SUSPEND_MAX_TIME_MILLIS = 1000 * 15;
    private $CONSUMER_TIMEOUT_MILLIS_WHEN_SUSPEND = 1000 * 30;
    /**
     * Whether update subscription relationship when every pull
     */
    private $postSubscriptionWhenPull = false;
    private $consumerGroup;
    private $namesrvAddr;
    /**
     * 启动的消费线程数
     * @var int
     */
    private $consumeThreadNum = 1;
    /**
     * Batch consumption size
     */
    private $consumeMessageBatchMaxSize = 1;
    /**
     * Maximum amount of time in minutes a message may block the consuming thread.
     */
    private $consumeTimeout = 15;
    /**
     * Batch pull size
     */
    private $pullBatchSize = 1;
    /**
     * Message pull Interval
     */
    private $pullInterval = 0;
    /**
     * Suspending pulling time for cases requiring slow pulling like flow-control scenario.
     */
    private $suspendCurrentQueueTimeMillis = 1000;
    /**
     * @var RebalanceImpl
     */
    private $rebalanceImpl;
    private $serviceState = ServiceState::CREATE_JUST;
    /**
     * @var string[]
     */
    private $subscription;
    /**
     * @var MessageListener
     */
    private $messageListenerInner;
    /**
     * @var MessageModel
     */
    private $messageModel = MessageModel::CLUSTERING;
    /**
     * @var MQAsyncClientInstance
     */
    private $mqClientFactory;
    /**
     * @var AllocateMessageQueueStrategy
     */
    protected $allocateMessageQueueStrategy;
    /**
     * @var PullAPIWrapper
     */
    private $pullAPIWrapper;
    private $unitMode = false;
    /**
     * @var OffsetStore
     */
    private $offsetStore;
    /**
     * 是否顺序消费
     * @var bool
     */
    private $consumeOrderly = false;
    /**
     * @var ConsumeMessageService
     */
    private $consumeMessageService;
    private $pause = false;
    private $consumeFromWhere = ConsumeFromWhere::CONSUME_FROM_LAST_OFFSET;
    private $instanceName = "DEFAULT";
    /**
     * 消费的主题名
     * @var string
     */
    private $topic;

    /**
     * DefaultMQConsumer constructor.
     * @param $consumerGroup
     */
    public function __construct($consumerGroup)
    {
        $this->log = LoggerFactory::getLogger(DefaultMQConsumer::class);
        $this->consumerGroup = $consumerGroup;
        $this->rebalanceImpl = new RebalanceImpl($this);

        // 设置默认负载均衡策略
        $this->allocateMessageQueueStrategy = new AllocateMessageQueueAveragely();
    }

    public function subscribe(string $topic , string $subExpression){
        try {
            $this->topic = $topic;
            $subscriptionData = FilterAPI::buildSubscriptionData($this->consumerGroup,$topic, $subExpression);
            $this->rebalanceImpl->addSubscriptionData($topic, $subscriptionData);
        } catch (\Exception $e) {
            throw new RocketMQClientException("subscription exception". " error: ".$e->getMessage());
        }
    }

    /**
     * @param mixed $consumerGroup
     */
    public function setConsumerGroup($consumerGroup)
    {
        $this->consumerGroup = $consumerGroup;
    }

    /**
     * @param mixed $namesrvAddr
     */
    public function setNamesrvAddr($namesrvAddr)
    {
        $this->namesrvAddr = $namesrvAddr;
    }

    public function start(){
        switch ($this->serviceState) {
            case ServiceState::CREATE_JUST:
                $this->serviceState = ServiceState::START_FAILED;

                $this->copySubscription();

                // 创建mqinstance
                $this->mqClientFactory = MQClientInstanceFactory::createAsync($this->namesrvAddr);

                $this->rebalanceImpl->setConsumerGroup($this->consumerGroup);
                $this->rebalanceImpl->setMessageModel($this->messageModel);
                $this->rebalanceImpl->setAllocateMessageQueueStrategy($this->allocateMessageQueueStrategy);
                $this->rebalanceImpl->setmQClientFactory($this->mqClientFactory);

                $this->pullAPIWrapper = new PullAPIWrapper(
                    $this->mqClientFactory, $this->consumerGroup, $this->unitMode);

                switch ($this->messageModel) {
                    case MessageModel::BROADCASTING:
                        $this->offsetStore = new LocalFileOffsetStore($this->mqClientFactory, $this->consumerGroup);
                        break;
                    case MessageModel::CLUSTERING:
                        // 集群模式下，消费进度保存在broker
                        $this->offsetStore = new RemoteBrokerOffsetStore($this->mqClientFactory, $this->consumerGroup);
                        break;
                    default:
                        break;
                }
                $this->offsetStore->load();

                if ($this->messageListenerInner instanceof MessageListenerOrderly) {
                    // 顺序消费
                    $this->consumeOrderly = true;
                    $this->consumeMessageService = new ConsumeMessageOrderlyService($this, $this->messageListenerInner);
                } else if ($this->messageListenerInner instanceof MessageListenerConcurrently) {
                    // 并发消费
                    $this->consumeOrderly = false;
                    $this->consumeMessageService = new ConsumeMessageConcurrentlyService($this, $this->messageListenerInner);
                }
                $this->consumeMessageService->start();

                $this->mqClientFactory->registerConsumer($this->consumerGroup , $this);
                $this->serviceState = ServiceState::RUNNING;
                break;
            case ServiceState::RUNNING:
            case ServiceState::SHUTDOWN_ALREADY:
            case ServiceState::START_FAILED:
                throw new RocketMQClientException("consumer state is ".$this->serviceState);
        }

        $this->updateTopicSubscribeInfoWhenSubscriptionChanged();
        $this->mqClientFactory->start();
    }

    /**
     * @throws \Exception
     */
    private function copySubscription(){
        if (!empty($this->subscription)) {
            foreach ($this->subscription as $topic => $subString) {
                $subscriptionData = FilterAPI::buildSubscriptionData($this->consumerGroup, $topic, $subString);
                $this->rebalanceImpl->addSubscriptionData($topic, $subscriptionData);
            }
        }

        switch ($this->messageModel) {
            case MessageModel::BROADCASTING:
                break;
            case MessageModel::CLUSTERING:
                /**
                 * 设置重试主题，rocketmq消息重试是以组为单位的，重试主题是 %RETRY% + group，消费者启动时会自动订阅该主题，参与该主题的队列均衡
                 */
                $retryTopic = MixUtil::getRetryTopic($this->consumerGroup);
                $subscriptionData = FilterAPI::buildSubscriptionData($this->consumerGroup,
                    $retryTopic, SubscriptionData::SUB_ALL);
                $this->rebalanceImpl->addSubscriptionData($retryTopic, $subscriptionData);
                break;
            default:
                break;
        }
    }

    private function updateTopicSubscribeInfoWhenSubscriptionChanged() {
        $subTable = $this->getSubscriptionInner();
        if (!empty($subTable)) {
            foreach ($subTable as $topic => $subscriptionData) {
                $this->mqClientFactory->updateTopicPublishInfoFromNamesrv($topic);
            }
        }
    }

    /**
     * 负载均衡
     */
    public function doRebalance(){
        if(!$this->pause){
            $this->rebalanceImpl->doRebalance($this->consumeOrderly);
        }
    }

    public function executePullRequestImmediately(PullRequest $pullRequest) {
        $this->mqClientFactory->getPullMessageService()->executePullRequestImmediately($pullRequest);
    }

    public function executePullRequestLater(PullRequest $pullRequest , int $delayTime) {
        $this->mqClientFactory->getPullMessageService()->executePullRequestLater($pullRequest , $delayTime);
    }

    private function makeSureStateOK(){
        if ($this->serviceState != ServiceState::RUNNING) {
            throw new RocketMQClientException("The consumer service state not OK, "
                . $this->serviceState);
        }
    }

    /**
     * 拉取消息
     * @param PullRequest $pullRequest
     */
    public function pullMessage(PullRequest $pullRequest){
        // var_dump("pullRequest:" . json_encode($pullRequest));
        $processQueue = $pullRequest->getProcessQueue();
        if ($processQueue->isDropped()) {
            $this->log->info("the pull request[{}] is dropped.", json_encode($pullRequest));
            return;
        }

        $pullRequest->getProcessQueue()->setLastPullTimestamp(TimeUtil::currentTimeMillis());

        try {
            $this->makeSureStateOK();
        } catch (RocketMQClientException $e) {
            $this->log->warn("pullMessage exception, consumer state not ok". " error: ".$e->getMessage());
            $this->executePullRequestLater($pullRequest, $this->pullTimeDelayMillsWhenException);
            return;
        }

        if ($this->isPause()) {
            // 被挂起，等待1s
            $this->log->warn("consumer was paused, execute pull request later. instanceName={}, group={}", $this->getInstanceName(), $this->consumerGroup);
            $this->executePullRequestLater($pullRequest, $this->PULL_TIME_DELAY_MILLS_WHEN_SUSPEND);
            return;
        }

        $cachedMessageCount = $processQueue->getMsgCount()->get();
        $cachedMessageSizeInMiB = $processQueue->getMsgSize()->get() / (1024 * 1024);

        if ($cachedMessageCount > $this->getPullThresholdForQueue()) {
            // ProcessQueue当前待处理消息大于1000，则触发流控，下个周期再处理
            $this->executePullRequestLater($pullRequest, $this->PULL_TIME_DELAY_MILLS_WHEN_FLOW_CONTROL);
            if (($this->queueFlowControlTimes++ % 1000) == 0) {
                $this->log->warn(
                    "the cached message count exceeds the threshold {}, so do flow control, minOffset={}, maxOffset={}, count={}, size={} MiB, pullRequest={}, flowControlTimes={}",
                    $this->getPullThresholdForQueue(), $processQueue->getMsgTreeMap()->firstKey(), $processQueue->getMsgTreeMap()->lastKey(), $cachedMessageCount, $cachedMessageSizeInMiB, json_encode($pullRequest), $this->queueFlowControlTimes);
            }
            // var_dump("liukong 1......");
            return;
        }

        if ($cachedMessageSizeInMiB > $this->getPullThresholdSizeForQueue()) {
            $this->executePullRequestLater($pullRequest, $this->PULL_TIME_DELAY_MILLS_WHEN_FLOW_CONTROL);
            if (($this->queueFlowControlTimes++ % 1000) == 0) {
                $this->log->warn(
                    "the cached message size exceeds the threshold {} MiB, so do flow control, minOffset={}, maxOffset={}, count={}, size={} MiB, pullRequest={}, flowControlTimes={}",
                    $this->getPullThresholdSizeForQueue(), $processQueue->getMsgTreeMap()->firstKey(), $processQueue->getMsgTreeMap()->lastKey(), $cachedMessageCount, $cachedMessageSizeInMiB, json_encode($pullRequest), $this->queueFlowControlTimes);
            }
            // var_dump("liukong 2......");
            return;
        }

        if (!$this->consumeOrderly) {
            if ($processQueue->getMaxSpan() > $this->getConsumeConcurrentlyMaxSpan()) {
                // 最大消息偏移量和最小消息偏移量的间距大于指定值，触发流控（考虑是一条消息重复消费造成堵塞，消息进度无法前进）
                $this->executePullRequestLater($pullRequest, $this->PULL_TIME_DELAY_MILLS_WHEN_FLOW_CONTROL);
                if (($this->queueMaxSpanFlowControlTimes++ % 1000) == 0) {
                    $this->log->warn(
                        "the queue's messages, span too long, so do flow control, minOffset={}, maxOffset={}, maxSpan={}, pullRequest={}, flowControlTimes={}",
                        $processQueue->getMsgTreeMap()->firstKey(), $processQueue->getMsgTreeMap()->lastKey(), $processQueue->getMaxSpan(),
                        json_encode($pullRequest), $this->queueMaxSpanFlowControlTimes);
                }
                // var_dump("liukong 3......");
                return;
            }
        } else {
            if ($processQueue->isLocked()) {
                if (!$pullRequest->isLockedFirst()) {
                    $offset = $this->rebalanceImpl->computePullFromWhere($pullRequest->getMessageQueue());
                    $brokerBusy = $offset < $pullRequest->getNextOffset();
                    $this->log->info("the first time to pull message, so fix offset from broker. pullRequest: {} NewOffset: {} brokerBusy: {}",
                        json_encode($pullRequest), $offset, $brokerBusy);
                    if ($brokerBusy) {
                        $this->log->info("[NOTIFYME]the first time to pull message, but pull request offset larger than broker consume offset. pullRequest: {} NewOffset: {}",
                            json_encode($pullRequest), $offset);
                    }

                    $pullRequest->setLockedFirst(true);
                    $pullRequest->setNextOffset($offset);
                }
            } else {
                $this->executePullRequestLater($pullRequest, $this->pullTimeDelayMillsWhenException);
                $this->log->info("pull message later because not locked in broker, {}", json_encode($pullRequest));
                // var_dump("liukong 4......");
                return;
            }
        }

        // 拉取该主题订阅消息
        $subscriptionInner = $this->rebalanceImpl->getSubscriptionInner();
        $subscriptionData = $subscriptionInner[$pullRequest->getMessageQueue()->getTopic()] ?? null;
        if (null == $subscriptionData) {
            $this->executePullRequestLater($pullRequest, $this->pullTimeDelayMillsWhenException);
            $this->log->warn("find the consumer's subscription failed, {}", $pullRequest);
            // var_dump("liukong 5......");
            return;
        }

        $beginTimestamp = TimeUtil::currentTimeMillis();

        // 定义一个闭包回调
        $pullMessageCallback = new PullMessageCallback(function (RemotingCommand $response) use (
            $pullRequest,$subscriptionData,$beginTimestamp,$processQueue){
            $pullResult = null;
            if ($response != null) {
                try {
                    $pullResult = $this->pullAPIWrapper->processPullResponse($response);
                    if($pullResult == null){
                        return;
                    }
                    // var_dump("pull result: ".json_encode($pullResult));
                    $this->pullbackOnSuccess($response , $pullResult , $pullRequest , $subscriptionData , $beginTimestamp , $processQueue);
                } catch (\Throwable $e) {
                    $this->pullbackOnException($e , $pullRequest);
                    // var_dump("pullResult: ".$e->getMessage());
                }
            }
        });


        $commitOffsetEnable = false;
        $commitOffsetValue = 0;
        if (MessageModel::CLUSTERING == $this->getMessageModel()) {
            $commitOffsetValue = $this->offsetStore->readOffset($pullRequest->getMessageQueue(), ReadOffsetType::READ_FROM_MEMORY);
            if ($commitOffsetValue > 0) {
                $commitOffsetEnable = true;
            }
        }

        $subExpression = null;
        $classFilter = false;
        $subscriptionInner = $this->rebalanceImpl->getSubscriptionInner();
        $sd = $subscriptionInner[$pullRequest->getMessageQueue()->getTopic()] ?? null;
        if ($sd != null) {
            if ($this->postSubscriptionWhenPull && !$sd->isClassFilterMode()) {
                $subExpression = $sd->getSubString();
            }

            $classFilter = $sd->isClassFilterMode();
        }

        $sysFlag = PullSysFlag::buildSysFlag(
            $commitOffsetEnable, // commitOffset
            true, // suspend
            $subExpression != null, // subscription
            $classFilter // class filter
        );

        try {
            $this->pullAPIWrapper->pullKernelImpl(
                $pullRequest->getMessageQueue(),
                $subExpression,
                $subscriptionData->getExpressionType(),
                $subscriptionData->getSubVersion(),
                $pullRequest->getNextOffset(),
                $this->pullBatchSize,
                $sysFlag,
                $commitOffsetValue,
                $this->BROKER_SUSPEND_MAX_TIME_MILLIS,
                $pullMessageCallback
            );
        } catch (\Exception $e) {
            $this->log->error("pullKernelImpl exception: ". $e->getMessage());
            $this->executePullRequestLater($pullRequest, $this->pullTimeDelayMillsWhenException);
            // var_dump("pullKernelImpl: ".$e->getFile()." " . $e->getLine() ." " . $e->getMessage());
        }
    }

    private function pullbackOnSuccess(RemotingCommand $remotingCommand ,
                                       PullResultExt $pullResult ,
                                       PullRequest $pullRequest ,
                                       $subscriptionData ,
                                       $beginTimestamp,
                                       ProcessQueue $processQueue){
        if ($pullResult != null) {
            $pullResult = $this->pullAPIWrapper->processPullResult($pullRequest->getMessageQueue(), $pullResult, $subscriptionData , $remotingCommand);
            switch ($pullResult->getPullStatus()) {
                case PullStatus::FOUND:
                    $prevRequestOffset = $pullRequest->getNextOffset();
                    $pullRequest->setNextOffset($pullResult->getNextBeginOffset());
                    /*$pullRT = TimeUtil::currentTimeMillis() - $beginTimestamp;
                    $this->getConsumerStatsManager().incPullRT($pullRequest->getConsumerGroup(),
                        pullRequest->getMessageQueue()->getTopic(), pullRT);*/

                    $firstMsgOffset = IntegerUtil::MAX_VALUE;
                    if (empty($pullResult->getMsgFoundList())) {
                        $this->executePullRequestImmediately($pullRequest);
                    } else {
                        $msgFoundList = $pullResult->getMsgFoundList();
                        $firstMsgOffset = $msgFoundList[0]->getQueueOffset();

                        /*$this->getConsumerStatsManager().incPullTPS($pullRequest->getConsumerGroup(),
                            pullRequest->getMessageQueue()->getTopic(), $pullResult->getMsgFoundList().size());*/

                        if($this->isConsumeOrderly()){
                            // 顺序消息
                            $dispatchToConsume = $processQueue->putMessage($pullResult->getMsgFoundList());
                        }else{
                            $dispatchToConsume = true;
                        }
                        // var_dump("consume: ".json_encode($msgFoundList));
                        $this->consumeMessageService->submitConsumeRequest(
                            $pullResult->getMsgFoundList(),
                            $processQueue,
                            $pullRequest->getMessageQueue(),
                            $dispatchToConsume);

                        if ($this->getPullInterval() > 0) {
                            // var_dump("executePullRequestLater: ".json_encode($pullRequest));
                            $this->executePullRequestLater($pullRequest, $this->getPullInterval());
                        } else {
                            // var_dump("executePullRequestImmediately: ".json_encode($pullRequest));
                            $this->executePullRequestImmediately($pullRequest);
                        }
                    }

                    if ($pullResult->getNextBeginOffset() < $prevRequestOffset
                        || $firstMsgOffset < $prevRequestOffset) {
                        $this->log->warn(
                            "[BUG] pull message result maybe data wrong, nextBeginOffset: {} firstMsgOffset: {} prevRequestOffset: {}",
                            $pullResult->getNextBeginOffset(),
                            $firstMsgOffset,
                            $prevRequestOffset);
                    }

                    break;
                case PullStatus::NO_NEW_MSG:
                    $pullRequest->setNextOffset($pullResult->getNextBeginOffset());
                    $this->correctTagsOffset($pullRequest);
                    $this->executePullRequestImmediately($pullRequest);
                    break;
                case PullStatus::NO_MATCHED_MSG:
                    $pullRequest->setNextOffset($pullResult->getNextBeginOffset());
                    $this->correctTagsOffset($pullRequest);
                    $this->executePullRequestImmediately($pullRequest);
                    break;
                case PullStatus::OFFSET_ILLEGAL:
                    $this->log->warn("the pull request offset illegal, {} {}",
                        json_encode($pullRequest), json_encode($pullResult));
                    $pullRequest->setNextOffset($pullResult->getNextBeginOffset());
                    $pullRequest->getProcessQueue()->setDropped(true);
                    ScheduleTaskUtil::after(10*1000 , function () use ($pullRequest){
                        try {
                            $this->offsetStore->updateOffset($pullRequest->getMessageQueue(),
                                $pullRequest->getNextOffset(), false);
                            $this->offsetStore->persist($pullRequest->getMessageQueue());
                            $this->rebalanceImpl->removeProcessQueue($pullRequest->getMessageQueue());
                            $this->log->warn("fix the pull request offset, {}", json_encode($pullRequest));
                        } catch (\Throwable $e) {
                            $this->log->error("executeTaskLater Exception". " error: ".$e->getMessage());
                        }
                    });
                    break;
                default:
                    break;
            }
        }
    }

    private function correctTagsOffset(PullRequest $pullRequest) {
        if (0 == $pullRequest->getProcessQueue()->getMsgCount()->get()) {
            $this->offsetStore->updateOffset($pullRequest->getMessageQueue(), $pullRequest->getNextOffset(), true);
        }
    }

    private function pullbackOnException(\Throwable $e , PullRequest $pullRequest){
        if (strpos($pullRequest->getMessageQueue()->getTopic() , MixUtil::$RETRY_GROUP_TOPIC_PREFIX) !== 0) {
            $this->log->warn("execute the pull request exception: ". $e->getMessage());
        }
        $this->executePullRequestLater($pullRequest , $this->pullTimeDelayMillsWhenException);
    }

    public function groupName()
    {
        return $this->consumerGroup;
    }

    public function messageModel()
    {
        return $this->messageModel;
    }

    public function consumeType()
    {
        return ConsumeType::CONSUME_PASSIVELY;
    }

    public function consumeFromWhere()
    {
        return $this->consumeFromWhere;
    }

    /**
     * @return SubscriptionData[]
     */
    public function subscriptions()
    {
        return array_values($this->rebalanceImpl->getSubscriptionInner());
    }

    public function persistConsumerOffset()
    {
        try{
            $mqs = $this->rebalanceImpl->getProcessQueueTable()->keys();
            $this->offsetStore->persistAll($mqs);
        }catch (\Exception $e){
            // var_dump("persistConsumerOffset error: ".$e->getMessage());
            $this->log->error("group: " . $this->consumerGroup . " persistConsumerOffset exception: " .  $e->getMessage());
        }
    }

    public function updateTopicSubscribeInfo(string $topic, $info)
    {
        $subTable = $this->getSubscriptionInner();
        if ($subTable != null) {
            if (isset($subTable[$topic])) {
                $this->rebalanceImpl->addTopicSubscribeInfo($topic, $info);
            }
        }
    }

    /**
     * @param string $topic
     * @return bool
     */
    public function isSubscribeTopicNeedUpdate(string $topic)
    {
        $subTable = $this->getSubscriptionInner();
        if ($subTable != null) {
            if (isset($subTable[$topic])) {
                $topicSubscribeInfoTable = $this->rebalanceImpl->getTopicSubscribeInfoTable();
                return !isset($topicSubscribeInfoTable[$topic]);
            }
        }

        return false;
    }

    /**
     * @param MessageExt[] $msgs
     * @param $consumerGroup
     */
    public function resetRetryAndNamespace(array $msgs, $consumerGroup) {
        $groupTopic = MixUtil::getRetryTopic($consumerGroup);
        foreach ($msgs as $msg) {
            $retryTopic = $msg->getProperty(MessageConst::PROPERTY_RETRY_TOPIC);
            if ($retryTopic != null && $groupTopic != $msg->getTopic()) {
                $msg->setTopic($retryTopic);
            }

            /*if (!empty($this->getNamespace())) {
                $msg->setTopic(NamespaceUtil.withoutNamespace(msg.getTopic(), this.defaultMQPushConsumer.getNamespace()));
            }*/
        }
    }

    public function getMaxReconsumeTimes() {
        // default reconsume times: 16
        if ($this->maxReconsumeTimes == -1) {
            return 16;
        } else {
            return $this->getMaxReconsumeTimes();
        }
    }

    public function sendMessageBack(MessageExt $msg, $delayLevel, $brokerName) {
        try {
            $brokerAddr = $this->mqClientFactory->findBrokerAddressInAdmin($brokerName);
            $client = $this->mqClientFactory->getOrCreateSyncClient($brokerAddr);
            $requestHeader = new ConsumerSendMsgBackRequestHeader();
            $requestHeader->setGroup($this->consumerGroup);
            $requestHeader->setOriginTopic($msg->getTopic());
            $requestHeader->setOffset($msg->getCommitLogOffset());
            $requestHeader->setDelayLevel($delayLevel);
            $requestHeader->setOriginMsgId($msg->getMsgId());
            $requestHeader->setMaxReconsumeTimes($this->getMaxReconsumeTimes());
            $request = RemotingCommand::createRequestCommand(RequestCode::$CONSUMER_SEND_MSG_BACK, $requestHeader);

            $response = $client->send($request);
            if($response->getCode() != ResponseCode::$SUCCESS){
                throw new RocketMQClientException($response->getCode(), $response->getRemark());
            }
        } catch (\Exception $e) {
            $this->log->error("sendMessageBack Exception, " . $this->consumerGroup . " " .$e->getMessage());

            $newMsg = new Message(MixUtil::getRetryTopic($this->consumerGroup), $msg->getBody());

            $originMsgId = $msg->getProperty(MessageConst::PROPERTY_ORIGIN_MESSAGE_ID);
            $newMsg->putProperty(MessageConst::PROPERTY_ORIGIN_MESSAGE_ID , $originMsgId);

            $newMsg->setFlag($msg->getFlag());
            $newMsg->setProperties($msg->getProperties());
            $newMsg->putProperty(MessageConst::PROPERTY_RETRY_TOPIC , $msg->getTopic());
            $newMsg->putProperty(MessageConst::PROPERTY_RECONSUME_TIME , ($msg->getReconsumeTimes() + 1));
            $newMsg->putProperty(MessageConst::PROPERTY_MAX_RECONSUME_TIMES , $this->getMaxReconsumeTimes());
            $newMsg->clearProperty(MessageConst::PROPERTY_TRANSACTION_PREPARED);
            $newMsg->setDelayTimeLevel(3 + $msg->getReconsumeTimes());
            $this->mqClientFactory->getDefaultMQProducer()->send($newMsg);
        } finally {
            // $msg->setTopic(NamespaceUtil.withoutNamespace(msg.getTopic(), this.defaultMQPushConsumer.getNamespace()));
        }
    }

    /**
     * 关机
     */
    public function shutdown(){
        if($this->serviceState == ServiceState::RUNNING){
            $this->serviceState = ServiceState::SHUTDOWN_ALREADY;
            // 停止把消息放到消费队列里
            $this->consumeMessageService->shutdown($this->awaitTerminationMillisWhenShutdown);
            $this->rebalanceImpl->shutdown();
            // 保存消费进度到broker
            $this->log->info("start to persist consumer offset to broker for ".$this->consumerGroup);
            $this->persistConsumerOffset();
        }
    }

    public function isUnitMode()
    {
        return $this->unitMode;
    }


    /**
     * @return SubscriptionData[]
     */
    public function getSubscriptionInner() {
        return $this->rebalanceImpl->getSubscriptionInner();
    }

    /**
     * @return string[]
     */
    public function getSubscription(): array
    {
        return $this->subscription;
    }

    /**
     * @param string[] $subscription
     */
    public function setSubscription(array $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * @param AllocateMessageQueueStrategy $allocateMessageQueueStrategy
     */
    public function setAllocateMessageQueueStrategy(AllocateMessageQueueStrategy $allocateMessageQueueStrategy)
    {
        $this->allocateMessageQueueStrategy = $allocateMessageQueueStrategy;
    }

    /**
     * @return OffsetStore
     */
    public function getOffsetStore(): OffsetStore
    {
        return $this->offsetStore;
    }

    /**
     * @param OffsetStore $offsetStore
     */
    public function setOffsetStore(OffsetStore $offsetStore)
    {
        $this->offsetStore = $offsetStore;
    }

    /**
     * @return mixed
     */
    public function getConsumeFromWhere()
    {
        return $this->consumeFromWhere;
    }

    /**
     * @param mixed $consumeFromWhere
     */
    public function setConsumeFromWhere($consumeFromWhere)
    {
        $this->consumeFromWhere = $consumeFromWhere;
    }

    /**
     * @return bool
     */
    public function isPause(): bool
    {
        return $this->pause;
    }

    /**
     * @param bool $pause
     */
    public function setPause(bool $pause)
    {
        $this->pause = $pause;
    }

    /**
     * @return int
     */
    public function getConsumeConcurrentlyMaxSpan(): int
    {
        return $this->consumeConcurrentlyMaxSpan;
    }

    /**
     * @param int $consumeConcurrentlyMaxSpan
     */
    public function setConsumeConcurrentlyMaxSpan(int $consumeConcurrentlyMaxSpan)
    {
        $this->consumeConcurrentlyMaxSpan = $consumeConcurrentlyMaxSpan;
    }

    /**
     * @return int
     */
    public function getPullThresholdForQueue(): int
    {
        return $this->pullThresholdForQueue;
    }

    /**
     * @param int $pullThresholdForQueue
     */
    public function setPullThresholdForQueue(int $pullThresholdForQueue)
    {
        $this->pullThresholdForQueue = $pullThresholdForQueue;
    }

    /**
     * @return int
     */
    public function getPullThresholdSizeForQueue(): int
    {
        return $this->pullThresholdSizeForQueue;
    }

    /**
     * @param int $pullThresholdSizeForQueue
     */
    public function setPullThresholdSizeForQueue(int $pullThresholdSizeForQueue)
    {
        $this->pullThresholdSizeForQueue = $pullThresholdSizeForQueue;
    }

    /**
     * @return string
     */
    public function getInstanceName(): string
    {
        return $this->instanceName;
    }

    /**
     * @param string $instanceName
     */
    public function setInstanceName(string $instanceName)
    {
        $this->instanceName = $instanceName;
    }

    /**
     * @return MessageListener
     */
    public function getMessageListener(): MessageListener
    {
        return $this->messageListenerInner;
    }

    /**
     * @param MessageListener $messageListenerInner
     */
    public function setMessageListener(MessageListener $messageListenerInner)
    {
        $this->messageListenerInner = $messageListenerInner;
    }

    /**
     * @return MessageModel
     */
    public function getMessageModel()
    {
        return $this->messageModel;
    }

    /**
     * @return PullAPIWrapper
     */
    public function getPullAPIWrapper(): PullAPIWrapper
    {
        return $this->pullAPIWrapper;
    }

    /**
     * @return int
     */
    public function getPullInterval(): int
    {
        return $this->pullInterval;
    }

    /**
     * @param int $pullInterval
     */
    public function setPullInterval(int $pullInterval)
    {
        $this->pullInterval = $pullInterval;
    }

    /**
     * @return bool
     */
    public function isConsumeOrderly(): bool
    {
        return $this->consumeOrderly;
    }

    /**
     * @return mixed
     */
    public function getConsumerGroup()
    {
        return $this->consumerGroup;
    }

    /**
     * @return int
     */
    public function getConsumeMessageBatchMaxSize(): int
    {
        return $this->consumeMessageBatchMaxSize;
    }

    /**
     * @param int $consumeMessageBatchMaxSize
     */
    public function setConsumeMessageBatchMaxSize(int $consumeMessageBatchMaxSize)
    {
        $this->consumeMessageBatchMaxSize = $consumeMessageBatchMaxSize;
    }

    /**
     * @return int
     */
    public function getConsumeTimeout(): int
    {
        return $this->consumeTimeout;
    }

    /**
     * @param int $consumeTimeout
     */
    public function setConsumeTimeout(int $consumeTimeout)
    {
        $this->consumeTimeout = $consumeTimeout;
    }

    /**
     * @return MQAsyncClientInstance
     */
    public function getMqClientFactory(): MQAsyncClientInstance
    {
        return $this->mqClientFactory;
    }

    /**
     * @return int
     */
    public function getSuspendCurrentQueueTimeMillis(): int
    {
        return $this->suspendCurrentQueueTimeMillis;
    }

    /**
     * @param int $suspendCurrentQueueTimeMillis
     */
    public function setSuspendCurrentQueueTimeMillis(int $suspendCurrentQueueTimeMillis)
    {
        $this->suspendCurrentQueueTimeMillis = $suspendCurrentQueueTimeMillis;
    }

    /**
     * @return RebalanceImpl
     */
    public function getRebalanceImpl(): RebalanceImpl
    {
        return $this->rebalanceImpl;
    }

    /**
     * @return int
     */
    public function getConsumeThreadNum(): int
    {
        return $this->consumeThreadNum;
    }

    /**
     * @param int $consumeThreadNum
     */
    public function setConsumeThreadNum(int $consumeThreadNum)
    {
        $this->consumeThreadNum = $consumeThreadNum;
    }

    /**
     * @param int $maxReconsumeTimes
     */
    public function setMaxReconsumeTimes(int $maxReconsumeTimes)
    {
        $this->maxReconsumeTimes = $maxReconsumeTimes;
    }

    /**
     * @return string
     */
    public function getTopic(): string
    {
        return $this->topic;
    }
}