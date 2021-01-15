<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\consumer\listener\ConsumeConcurrentlyStatus;
use ybrenLib\rocketmq\consumer\listener\ConsumeOrderlyContext;
use ybrenLib\rocketmq\consumer\listener\ConsumeOrderlyStatus;
use ybrenLib\rocketmq\consumer\listener\ConsumeReturnType;
use ybrenLib\rocketmq\entity\Message;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\util\CoroutineUtil;
use ybrenLib\rocketmq\util\IntegerUtil;
use ybrenLib\rocketmq\util\MixUtil;
use ybrenLib\rocketmq\util\ScheduleTaskUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class ConsumeMessageOrderlyService implements ConsumeMessageService
{
    /**
     * @var Logger
     */
    private $log;
    private static $MAX_TIME_CONSUME_CONTINUOUSLY = 60000;

    /**
     * @var DefaultMQConsumer
     */
    private $defaultMQConsumer;
    /**
     * @var MessageListenerOrderly
     */
    private $messageListener;
    /**
     * 消息消费队列
     * @var ConsumeMessageChannel
     */
    private $consumeMessageQueue;
    /**
     * 消息消费队列容量
     * @var int
     */
    private $consumeMessageQueueCapacity = 100;
    private $consumerGroup;
    /**
     * 是否关机
     * @var bool
     */
    private $stopped = false;

    /**
     * ConsumeMessageOrderlyService constructor.
     * @param DefaultMQConsumer $defaultMQConsumer
     * @param MessageListener $messageListener
     */
    public function __construct(DefaultMQConsumer $defaultMQConsumer, MessageListener $messageListener)
    {
        $this->log = LoggerFactory::getLogger(ConsumeMessageOrderlyService::class);
        $this->defaultMQConsumer = $defaultMQConsumer;
        $this->messageListener = $messageListener;
        $this->consumeMessageQueue = new ConsumeMessageChannel($this->consumeMessageQueueCapacity);
        $this->consumerGroup = $defaultMQConsumer->getConsumerGroup();
    }

    function start()
    {
        for($i = 1;$i <= $this->defaultMQConsumer->getConsumeThreadNum();$i++){
            CoroutineUtil::run(function () {
                while (!$this->isStopped()){
                    $consumeRequest = $this->consumeMessageQueue->popMessage();
                    if($consumeRequest !== false){
                        $this->consumeMessage($consumeRequest);
                    }
                }
            });
        }
    }

    /**
     * 关机
     * @param int $awaitTerminateMillis
     */
    function shutdown(int $awaitTerminateMillis)
    {
        $this->log->info("start to close consumeMessageService for ".$this->consumerGroup);
        $this->setStopped(true);
        $now = TimeUtil::currentTimeMillis();
        while ($this->consumeMessageQueue->length() > 0 && (TimeUtil::currentTimeMillis() - $now) < $awaitTerminateMillis){
            $this->log->info("wait to clean consumeMessageQueue......");
            sleep(1);
        }
        $this->consumeMessageQueue->close();
        $this->log->info("success to close consumeMessageService for ".$this->consumerGroup);
    }

    function updateCorePoolSize(int $corePoolSize)
    {
        // TODO: Implement updateCorePoolSize() method.
    }

    function incCorePoolSize()
    {
        // TODO: Implement incCorePoolSize() method.
    }

    function decCorePoolSize()
    {
        // TODO: Implement decCorePoolSize() method.
    }

    function getCorePoolSize()
    {
        // TODO: Implement getCorePoolSize() method.
    }

    function consumeMessageDirectly(MessageExt $msg, string $brokerName)
    {
        // TODO: Implement consumeMessageDirectly() method.
    }

    function submitConsumeRequest(array $msgs, ProcessQueue $processQueue, MessageQueue $messageQueue, bool $dispathToConsume)
    {
        if($dispathToConsume){
            $consumeRequest = new ConsumeRequest($msgs, $processQueue, $messageQueue);
            if(!$this->consumeMessageQueue->pushMessage($consumeRequest)){
                // 队列满了，延迟消费
                $this->submitConsumeRequestLater($processQueue , $messageQueue , 5*1000);
            }
        }
    }

    public function submitConsumeRequestLater(ProcessQueue $processQueue, MessageQueue $messageQueue , $suspendTimeMillis){
        $timeMillis = $suspendTimeMillis;
        if ($timeMillis == -1) {
            $timeMillis = $this->defaultMQConsumer->getSuspendCurrentQueueTimeMillis();
        }

        if ($timeMillis < 10) {
            $timeMillis = 10;
        } else if ($timeMillis > 30000) {
            $timeMillis = 30000;
        }
        ScheduleTaskUtil::after($timeMillis , function () use ($processQueue , $messageQueue){
            $this->submitConsumeRequest(null , $processQueue , $messageQueue , true);
        });
    }

    public function tryLockLaterAndReconsume(MessageQueue $mq, ProcessQueue $processQueue,
        $delayMills) {
        ScheduleTaskUtil::after($delayMills , function () use ($mq , $processQueue){
            $lockOK = $this->lockOneMQ($mq);
                if ($lockOK) {
                    $this->submitConsumeRequestLater($processQueue, $mq, 10);
                } else {
                    $this->submitConsumeRequestLater($processQueue, $mq, 3000);
                }
        });
    }

    public function lockOneMQ(MessageQueue $mq) {
        if (!$this->stopped) {
            return $this->defaultMQConsumer->getRebalanceImpl()->lock($mq);
        }
        return false;
    }

    public function consumeMessage(ConsumeRequest $consumeRequest){
        $processQueue = $consumeRequest->getProcessQueue();
        $messageQueue = $consumeRequest->getMessageQueue();

        if($this->isStopped()){
            return;
        }else if ($processQueue->isDropped()) {
            $this->log->info("the message queue not be able to consume, because it's dropped. group={} {}", $this->consumerGroup, json_encode($messageQueue));
            return;
        }

        $messageQueue->getConsumeLock()->tryLock();
        try{
            if (MessageModel::BROADCASTING == $this->defaultMQConsumer->messageModel()
                || ($processQueue->isLocked() && !$processQueue->isLockExpired())) {
                $beginTime = TimeUtil::currentTimeMillis();
                    for ($continueConsume = true; $continueConsume; ) {
                    if ($processQueue->isDropped()) {
                        $this->log->warn("the message queue not be able to consume, because it's dropped. {}", json_encode($messageQueue));
                        break;
                    }

                    if (MessageModel::CLUSTERING == $this->defaultMQConsumer->messageModel()
                        && !$processQueue->isLocked()) {
                        $this->log->warn("the message queue not locked, so consume later, {}", json_encode($messageQueue));
                        $this->tryLockLaterAndReconsume($messageQueue, $processQueue, 10);
                        break;
                    }

                    if (MessageModel::CLUSTERING == $this->defaultMQConsumer->messageModel()
                        && $processQueue->isLockExpired()) {
                        $this->log->warn("the message queue lock expired, so consume later, {}", json_encode($messageQueue));
                        $this->tryLockLaterAndReconsume($messageQueue, $processQueue, 10);
                        break;
                    }

                    $interval = TimeUtil::currentTimeMillis() - $beginTime;
                        if ($interval > self::$MAX_TIME_CONSUME_CONTINUOUSLY) {
                            $this->tryLockLaterAndReconsume($messageQueue, $processQueue, 10);
                            break;
                        }

                        $consumeBatchSize = $this->defaultMQConsumer->getConsumeMessageBatchMaxSize();

                        $msgs = $processQueue->takeMessages($consumeBatchSize);
                        $this->defaultMQConsumer->resetRetryAndNamespace($msgs, $this->defaultMQConsumer->getConsumerGroup());
                        if (!empty($msgs)) {
                            $context = new ConsumeOrderlyContext($messageQueue);

                            $status = null;

                            $consumeMessageContext = null;
                            /*if (ConsumeMessageOrderlyService.this.defaultMQPushConsumerImpl.hasHook()) {
                                consumeMessageContext = new ConsumeMessageContext();
                                consumeMessageContext
                                .setConsumerGroup(ConsumeMessageOrderlyService.this.defaultMQPushConsumer.getConsumerGroup());
                                consumeMessageContext.setNamespace(defaultMQPushConsumer.getNamespace());
                                consumeMessageContext.setMq(messageQueue);
                                consumeMessageContext.setMsgList(msgs);
                                consumeMessageContext.setSuccess(false);
                                // init the consume context type
                                consumeMessageContext.setProps(new HashMap<String, String>());
                                ConsumeMessageOrderlyService.this.defaultMQPushConsumerImpl.executeHookBefore(consumeMessageContext);
                            }*/

                            $beginTimestamp = TimeUtil::currentTimeMillis();
                            $returnType = ConsumeReturnType::SUCCESS;
                            $hasException = false;
                            try {
                                $processQueue->getLockConsume()->tryLock();
                                if ($processQueue->isDropped()) {
                                    $this->log->warn("consumeMessage, the message queue not be able to consume, because it's dropped. {}",
                                        json_encode($messageQueue));
                                    break;
                                }

                                $status = $this->messageListener->consumeMessage($msgs, $context);
                            } catch (\Throwable $e) {
                                $this->log->warn("consumeMessage exception: {} Group: {} Msgs: {} MQ: {}",
                                    $e->getMessage(),
                                    $this->consumerGroup,
                                    json_encode($msgs),
                                    json_encode($messageQueue));
                                $hasException = true;
                            } finally {
                                $processQueue->getLockConsume()->release();
                            }

                            if (null == $status
                                || ConsumeOrderlyStatus::ROLLBACK == $status
                                || ConsumeOrderlyStatus::SUSPEND_CURRENT_QUEUE_A_MOMENT == $status) {
                                $this->log->warn("consumeMessage Orderly return not OK, Group: {} Msgs: {} MQ: {}",
                                    $this->consumerGroup,
                                    json_encode($msgs),
                                    json_encode($messageQueue));
                            }

                            $consumeRT = TimeUtil::currentTimeMillis() - $beginTimestamp;
                            if (null == $status) {
                                if ($hasException) {
                                    $returnType = ConsumeReturnType::EXCEPTION;
                                } else {
                                    $returnType = ConsumeReturnType::RETURNNULL;
                                }
                            } else if ($consumeRT >= $this->defaultMQConsumer->getConsumeTimeout() * 60 * 1000) {
                                $returnType = ConsumeReturnType::TIME_OUT;
                            } else if (ConsumeConcurrentlyStatus::RECONSUME_LATER == $status) {
                                $returnType = ConsumeReturnType::FAILED;
                            } else if (ConsumeConcurrentlyStatus::CONSUME_SUCCESS == $status) {
                                $returnType = ConsumeReturnType::SUCCESS;
                            }

                            /*if (ConsumeMessageOrderlyService.this.defaultMQPushConsumerImpl.hasHook()) {
                                consumeMessageContext.getProps().put(MixAll.CONSUME_CONTEXT_TYPE, returnType.name());
                            }*/

                            if (null == $status) {
                                $status = ConsumeOrderlyStatus::SUSPEND_CURRENT_QUEUE_A_MOMENT;
                            }

                            /*if (ConsumeMessageOrderlyService.this.defaultMQPushConsumerImpl.hasHook()) {
                                consumeMessageContext.setStatus(status.toString());
                                consumeMessageContext
                                .setSuccess(ConsumeOrderlyStatus.SUCCESS == status || ConsumeOrderlyStatus.COMMIT == status);
                                ConsumeMessageOrderlyService.this.defaultMQPushConsumerImpl.executeHookAfter(consumeMessageContext);
                            }*/

                            /*ConsumeMessageOrderlyService.this.getConsumerStatsManager()
                            .incConsumeRT(ConsumeMessageOrderlyService.this.consumerGroup, messageQueue.getTopic(), consumeRT);*/

                            $continueConsume = $this->processConsumeResult($status, $context, $consumeRequest);
                        } else {
                            $continueConsume = false;
                        }
                    }
                } else {
                if ($processQueue->isDropped()) {
                    $this->log->warn("the message queue not be able to consume, because it's dropped. {}", json_encode($messageQueue));
                    return;
                }

                $this->tryLockLaterAndReconsume($messageQueue, $processQueue, 100);
            }
        } finally {
            $messageQueue->getConsumeLock()->release();
        }
    }

    /**
     * @param $status
     * @param ConsumeOrderlyContext $context
     * @param ConsumeRequest $consumeRequest
     * @return bool
     */
    public function processConsumeResult(
        $status,
        ConsumeOrderlyContext $context,
        ConsumeRequest $consumeRequest
    ){
        $continueConsume = true;
        $commitOffset = -1;
        $msgs = $consumeRequest->getMsgs();
        if (/*$context->isAutoCommit()*/true) {
            switch ($status) {
                case ConsumeOrderlyStatus::COMMIT:
                case ConsumeOrderlyStatus::ROLLBACK:
                    $this->log->warn("the message queue consume result is illegal, we think you want to ack these message {}",
                        $consumeRequest->getMessageQueue());
                case ConsumeOrderlyStatus::SUCCESS:
                    $commitOffset = $consumeRequest->getProcessQueue()->commit();
                    // this.getConsumerStatsManager().incConsumeOKTPS(consumerGroup, $consumeRequest->getMessageQueue().getTopic(), msgs.size());
                    break;
                case ConsumeOrderlyStatus::SUSPEND_CURRENT_QUEUE_A_MOMENT:
                    //     this.getConsumerStatsManager().incConsumeFailedTPS(consumerGroup, $consumeRequest->getMessageQueue().getTopic(), msgs.size());
                    if ($this->checkReconsumeTimes($msgs)) {
                        $consumeRequest->getProcessQueue()->makeMessageToCosumeAgain($msgs);
                        $this->submitConsumeRequestLater(
                            $consumeRequest->getProcessQueue(),
                            $consumeRequest->getMessageQueue(),
                            $context->getSuspendCurrentQueueTimeMillis());
                        $continueConsume = false;
                    } else {
                        $commitOffset = $consumeRequest->getProcessQueue()->commit();
                    }
                    break;
                default:
                    break;
            }
        }else{

        }

        if ($commitOffset >= 0 && !$consumeRequest->getProcessQueue()->isDropped()) {
            $this->defaultMQConsumer->getOffsetStore()->updateOffset($consumeRequest->getMessageQueue(), $commitOffset, false);
        }

        return $continueConsume;
    }

    /**
     * @param MessageExt[] $msgs
     * @return bool
     */
    private function checkReconsumeTimes($msgs) {
        $suspend = false;
        if (!empty($msgs)) {
            foreach ($msgs as $msg) {
                if ($msg->getReconsumeTimes() >= $this->getMaxReconsumeTimes()) {
                    $msg->putProperty(MessageConst::PROPERTY_RECONSUME_TIME , $msg->getReconsumeTimes());
                    if (!$this->sendMessageBack($msg)) {
                        $suspend = true;
                        $msg->setReconsumeTimes($msg->getReconsumeTimes() + 1);
                    }
                } else {
                    $suspend = true;
                    $msg->setReconsumeTimes($msg->getReconsumeTimes() + 1);
                }
            }
        }
        return $suspend;
    }

    /**
     * @return int
     */
    private function getMaxReconsumeTimes() {
        // default reconsume times: Integer.MAX_VALUE
        if ($this->defaultMQConsumer->getMaxReconsumeTimes() == -1) {
            return IntegerUtil::MAX_VALUE;
        } else {
            return $this->defaultMQConsumer->getMaxReconsumeTimes();
        }
    }

    /**
     * @param MessageExt $msg
     * @return bool
     */
    public function sendMessageBack(MessageExt $msg) {
        try{
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
            $this->defaultMQConsumer->getMqClientFactory()->getDefaultMQProducer()->send($newMsg);
            return true;
        }catch (\Exception $e){
            $this->log->error("sendMessageBack exception, group: " . $this->consumerGroup . " msg: " . json_encode($msg) . " error: " . $e->getMessage());
        }
        return false;
    }

    /**
     * @param int $consumeMessageQueueCapacity
     */
    public function setConsumeMessageQueueCapacity(int $consumeMessageQueueCapacity)
    {
        $this->consumeMessageQueueCapacity = $consumeMessageQueueCapacity;
    }

    /**
     * @return bool
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * @param bool $stopped
     */
    public function setStopped(bool $stopped)
    {
        $this->stopped = $stopped;
    }
}