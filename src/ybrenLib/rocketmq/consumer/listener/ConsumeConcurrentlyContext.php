<?php
namespace ybrenLib\rocketmq\consumer\listener;

use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\util\IntegerUtil;

class ConsumeConcurrentlyContext
{
    /**
     * @var MessageQueue
     */
    private $messageQueue;
    /**
     * 重试延时等级，默认0由broker控制，-1不重试，大于0则client自己定义
     * Message consume retry strategy<br>
     * -1,no retry,put into DLQ directly<br>
     * 0,broker control retry frequency<br>
     * >0,client control retry frequency
     */
    private $delayLevelWhenNextConsume = 0;

    /**
     * @var int
     */
    private $ackIndex = IntegerUtil::MAX_VALUE;

    /**
     * ConsumeConcurrentlyContext constructor.
     * @param MessageQueue $messageQueue
     */
    public function __construct(MessageQueue $messageQueue)
    {
        $this->messageQueue = $messageQueue;
    }

    /**
     * @return MessageQueue
     */
    public function getMessageQueue(): MessageQueue
    {
        return $this->messageQueue;
    }

    /**
     * @param MessageQueue $messageQueue
     */
    public function setMessageQueue(MessageQueue $messageQueue)
    {
        $this->messageQueue = $messageQueue;
    }

    /**
     * @return int
     */
    public function getDelayLevelWhenNextConsume(): int
    {
        return $this->delayLevelWhenNextConsume;
    }

    /**
     * @param int $delayLevelWhenNextConsume
     */
    public function setDelayLevelWhenNextConsume(int $delayLevelWhenNextConsume)
    {
        $this->delayLevelWhenNextConsume = $delayLevelWhenNextConsume;
    }

    /**
     * @return int
     */
    public function getAckIndex()
    {
        return $this->ackIndex;
    }
}