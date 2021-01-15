<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\entity\MessageQueue;

class PullRequest extends Column
{
    // 消费者组
    protected $consumerGroup;
    /**
     * 待拉取消费队列
     * MessageQueue
     * @var MessageQueue
     */
    protected $messageQueue;
    /**
     * 消息处理队列，从broker拉取到的消息先存到ProcessQueue，再提交到消费者线程去消费
     * @var ProcessQueue
     */
    protected $processQueue;
    // 待拉取的MessageQueue偏移量
    protected $nextOffset;
    // 是否被锁定
    protected $lockedFirst = false;

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
     * @return mixed
     */
    public function getMessageQueue()
    {
        return $this->messageQueue;
    }

    /**
     * @param mixed $messageQueue
     */
    public function setMessageQueue($messageQueue)
    {
        $this->messageQueue = $messageQueue;
    }

    /**
     * @return ProcessQueue
     */
    public function getProcessQueue(): ProcessQueue
    {
        return $this->processQueue;
    }

    /**
     * @param ProcessQueue $processQueue
     */
    public function setProcessQueue(ProcessQueue $processQueue)
    {
        $this->processQueue = $processQueue;
    }

    /**
     * @return mixed
     */
    public function getNextOffset()
    {
        return $this->nextOffset;
    }

    /**
     * @param mixed $nextOffset
     */
    public function setNextOffset($nextOffset)
    {
        $this->nextOffset = $nextOffset;
    }

    /**
     * @return bool
     */
    public function isLockedFirst(): bool
    {
        return $this->lockedFirst;
    }

    /**
     * @param bool $lockedFirst
     */
    public function setLockedFirst(bool $lockedFirst)
    {
        $this->lockedFirst = $lockedFirst;
    }
}