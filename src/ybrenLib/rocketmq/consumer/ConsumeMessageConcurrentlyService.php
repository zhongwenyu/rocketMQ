<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\consumer\listener\ConsumeConcurrentlyContext;
use ybrenLib\rocketmq\consumer\listener\ConsumeConcurrentlyStatus;
use ybrenLib\rocketmq\consumer\listener\ConsumeReturnType;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\util\CoroutineUtil;
use ybrenLib\rocketmq\util\ScheduleTaskUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class ConsumeMessageConcurrentlyService implements ConsumeMessageService
{
    /**
     * @var Logger
     */
    private $log;
    /**
     * @var DefaultMQConsumer
     */
    private $defaultMQConsumer;
    /**
     * @var MessageListenerConcurrently
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
        $this->log = LoggerFactory::getLogger(ConsumeMessageConcurrentlyService::class);
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
                    // var_dump("pop consumeRequest: ".json_encode($consumeRequest));
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
        $consumeBatchSize = $this->defaultMQConsumer->getConsumeMessageBatchMaxSize();
        $msgsSize = count($msgs);
        if ($msgsSize <= $consumeBatchSize) {
            $this->pushComsumeRequestToChannel($msgs , $processQueue , $messageQueue);
        } else {
            for ($total = 0; $total < $msgsSize; ) {
                $msgThis = [];
                for ($i = 0; $i < $consumeBatchSize; $i++, $total++) {
                    if ($total < $msgsSize) {
                        $msgThis[] = $msgs[$total];
                    } else {
                        break;
                    }
                }
                $this->pushComsumeRequestToChannel($msgThis , $processQueue , $messageQueue);
            }
        }
    }

    /**
     * @param array $msgs
     * @param ProcessQueue $processQueue
     * @param MessageQueue $messageQueue
     */
    private function pushComsumeRequestToChannel(array $msgs, ProcessQueue $processQueue, MessageQueue $messageQueue){
        CoroutineUtil::run(function () use ($msgs, $processQueue, $messageQueue){
            if($this->isStopped()){
                return;
            }
            $consumeRequest = new ConsumeRequest($msgs, $processQueue, $messageQueue);
            $pushResult = $this->consumeMessageQueue->pushMessage($consumeRequest);
            // var_dump("push consumeRequest: ".json_encode($consumeRequest));
            if(!$pushResult){
                // 队列满了，延迟消费
                $this->submitConsumeRequestLater($msgs , $processQueue , $messageQueue);
            }
        });
    }

    public function submitConsumeRequestLater(array $msgs, ProcessQueue $processQueue, MessageQueue $messageQueue){
        ScheduleTaskUtil::after(5*1000 , function () use ($msgs , $processQueue , $messageQueue){
            $this->submitConsumeRequest($msgs , $processQueue , $messageQueue , true);
        });
    }

    /**
     * @param int $consumeThreadNum
     */
    public function setConsumeThreadNum(int $consumeThreadNum)
    {
        $this->consumeThreadNum = $consumeThreadNum;
    }

    /**
     * @param int $consumeMessageQueueCapacity
     */
    public function setConsumeMessageQueueCapacity(int $consumeMessageQueueCapacity)
    {
        $this->consumeMessageQueueCapacity = $consumeMessageQueueCapacity;
    }

    public function consumeMessage(ConsumeRequest $consumeRequest){
        $msgs = $consumeRequest->getMsgs();
        $processQueue = $consumeRequest->getProcessQueue();
        $messageQueue = $consumeRequest->getMessageQueue();
        if ($processQueue->isDropped()) {
            $this->log->info("the message queue not be able to consume, because it's dropped. group={} {}", $this->consumerGroup, json_encode($messageQueue));
            return;
        }

        $listener = $this->messageListener;
        $context = new ConsumeConcurrentlyContext($messageQueue);
        $status = null;
        $this->defaultMQConsumer->resetRetryAndNamespace($msgs, $this->defaultMQConsumer->getConsumerGroup());

        $consumeMessageContext = null;
        /*if ($this->defaultMQPushConsumerImpl.hasHook()) {
            consumeMessageContext = new ConsumeMessageContext();
            consumeMessageContext.setNamespace($this->defaultMQConsumer->getNamespace());
            consumeMessageContext.setConsumerGroup($this->defaultMQConsumer->getConsumerGroup());
            consumeMessageContext.setProps(new HashMap<String, String>());
            consumeMessageContext.setMq(messageQueue);
            consumeMessageContext.setMsgList(msgs);
            consumeMessageContext.setSuccess(false);
            $this->defaultMQPushConsumerImpl.executeHookBefore(consumeMessageContext);
        }*/

        $beginTimestamp = TimeUtil::currentTimeMillis();
        $hasException = false;
        $returnType = ConsumeReturnType::SUCCESS;
        try {
            if (!empty($msgs)) {
                foreach ($msgs as $msg) {
                    $msg->putProperty(MessageConst::PROPERTY_CONSUME_START_TIMESTAMP , TimeUtil::currentTimeMillis());
                }
            }
            $status = $listener->consumeMessage($msgs, $context);
        } catch (\Throwable $e) {
            $this->log->warn("consumeMessage exception: {} Group: {} Msgs: {} MQ: {}",
                $e->getMessage(),
                $this->consumerGroup,
                json_encode($msgs),
                json_encode($messageQueue));
            $hasException = true;
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

        /*if ($this->defaultMQConsumer.hasHook()) {
            consumeMessageContext.getProps().put(MixAll.CONSUME_CONTEXT_TYPE, returnType.name());
        }*/

        if (null == $status) {
            $this->log->warn("consumeMessage return null, Group: {} Msgs: {} MQ: {}",
                $this->consumerGroup,
                json_encode($msgs),
                json_encode($messageQueue));
            $status = ConsumeConcurrentlyStatus::RECONSUME_LATER;
        }

        /*if ($this->defaultMQConsumer.hasHook()) {
            consumeMessageContext.setStatus(status.toString());
            consumeMessageContext.setSuccess(ConsumeConcurrentlyStatus::CONSUME_SUCCESS == status);
            $this->defaultMQPushConsumerImpl.executeHookAfter(consumeMessageContext);
        }*/

        //    $this->getConsumerStatsManager()->incConsumeRT($this->consumerGroup, $messageQueue->getTopic(), $consumeRT);

        if (!$processQueue->isDropped()) {
            $this->processConsumeResult($status, $context, $consumeRequest);
        } else {
            $this->log->warn("processQueue is dropped without process consume result. messageQueue={}, msgs={}", json_encode($messageQueue), json_encode($msgs));
        }
    }

    public function processConsumeResult(
        $status,
        ConsumeConcurrentlyContext $context,
        ConsumeRequest $consumeRequest
    ) {
        $ackIndex = $context->getAckIndex();

        if (empty($consumeRequest->getMsgs()))
            return;

        $msgsSize = count($consumeRequest->getMsgs());
        switch ($status) {
            case ConsumeConcurrentlyStatus::CONSUME_SUCCESS:
                if ($ackIndex >= $msgsSize) {
                    $ackIndex = $msgsSize - 1;
                }
                $ok = $ackIndex + 1;
                $failed = $msgsSize - $ok;
              //  this.getConsumerStatsManager().incConsumeOKTPS(consumerGroup, consumeRequest.getMessageQueue().getTopic(), ok);
              //  this.getConsumerStatsManager().incConsumeFailedTPS(consumerGroup, consumeRequest.getMessageQueue().getTopic(), failed);
                break;
            case ConsumeConcurrentlyStatus::RECONSUME_LATER:
                $ackIndex = -1;
                /*this.getConsumerStatsManager().incConsumeFailedTPS(consumerGroup, consumeRequest.getMessageQueue().getTopic(),
                    consumeRequest.getMsgs().size());*/
                break;
            default:
                break;
        }

        switch ($this->defaultMQConsumer->getMessageModel()) {
            case MessageModel::BROADCASTING:
                foreach ($consumeRequest->getMsgs() as $msg) {
                    $this->log->warn("BROADCASTING, the message consume failed, drop it, {}", json_encode($msg));
                }
                break;
            case MessageModel::CLUSTERING:
                $msgBackFailed = [];
                $remainMsgs = [];
                $msgs = $consumeRequest->getMsgs();
                for ($i = $ackIndex + 1; $i < $msgsSize; $i++) {
                    // 如果消息消费成功，$ackIndex = $msgsSize - 1，这里并不会执行
                    $msg = $msgs[$i];
                    $result = $this->sendMessageBack($msg, $context);
                    if (!$result) {
                        $msg->setReconsumeTimes($msg->getReconsumeTimes() + 1);
                        $msgBackFailed[] = $msg;
                    }else{
                        $remainMsgs[] = $msg;
                    }
                }

                if (!empty($msgBackFailed)) {
                    // var_dump("fail msgs: ".json_encode($msgBackFailed));
                    $consumeRequest->setMsgs($remainMsgs);
                    $this->submitConsumeRequestLater($msgBackFailed, $consumeRequest->getProcessQueue(), $consumeRequest->getMessageQueue());
                }
                break;
            default:
                break;
        }

        $offset = $consumeRequest->getProcessQueue()->removeMessage($consumeRequest->getMsgs());
        if ($offset >= 0 && !$consumeRequest->getProcessQueue()->isDropped()) {
            $this->defaultMQConsumer->getOffsetStore()->updateOffset($consumeRequest->getMessageQueue(), $offset, true);
        }
    }

    public function sendMessageBack(MessageExt $msg, ConsumeConcurrentlyContext $context) {
        $delayLevel = $context->getDelayLevelWhenNextConsume();

        // Wrap topic with namespace before sending back message.
        //  $msg->setTopic(this.defaultMQPushConsumer.withNamespace(msg.getTopic()));
        try {
            $this->defaultMQConsumer->sendMessageBack($msg, $delayLevel, $context->getMessageQueue()->getBrokerName());
            return true;
        } catch (\Exception $e) {
            $this->log->error("sendMessageBack exception, group: " . $this->consumerGroup . " msg: " . json_encode($msg));
        }

        return false;
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