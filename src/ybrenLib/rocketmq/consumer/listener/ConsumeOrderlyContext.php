<?php
namespace ybrenLib\rocketmq\consumer\listener;

use ybrenLib\rocketmq\entity\MessageQueue;

class ConsumeOrderlyContext
{
    /**
     * @var MessageQueue
     */
    private $messageQueue;
    private $autoCommit = true;
    private $suspendCurrentQueueTimeMillis = -1;

    /**
     * ConsumeOrderlyContext constructor.
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
     * @return bool
     */
    public function isAutoCommit(): bool
    {
        return $this->autoCommit;
    }

    /**
     * @param bool $autoCommit
     */
    public function setAutoCommit(bool $autoCommit)
    {
        $this->autoCommit = $autoCommit;
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
}