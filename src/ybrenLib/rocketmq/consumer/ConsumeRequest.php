<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\entity\MessageQueue;

class ConsumeRequest extends Column
{
    /**
     * @var MessageExt[]
     */
    protected $msgs;
    /**
     * @var ProcessQueue
     */
    protected $processQueue;
    /**
     * @var MessageQueue
     */
    protected $messageQueue;

    /**
     * ConsumeRequest constructor.
     * @param MessageExt[] $msgs
     * @param ProcessQueue $processQueue
     * @param MessageQueue $messageQueue
     */
    public function __construct(array $msgs, ProcessQueue $processQueue, MessageQueue $messageQueue)
    {
        $this->msgs = $msgs;
        $this->processQueue = $processQueue;
        $this->messageQueue = $messageQueue;
    }

    /**
     * @return MessageExt[]
     */
    public function getMsgs(): array
    {
        return $this->msgs;
    }

    /**
     * @return ProcessQueue
     */
    public function getProcessQueue(): ProcessQueue
    {
        return $this->processQueue;
    }

    /**
     * @return MessageQueue
     */
    public function getMessageQueue(): MessageQueue
    {
        return $this->messageQueue;
    }

    /**
     * @param MessageExt[] $msgs
     */
    public function setMsgs(array $msgs)
    {
        $this->msgs = $msgs;
    }
}