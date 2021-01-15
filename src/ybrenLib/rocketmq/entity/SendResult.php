<?php
namespace ybrenLib\rocketmq\entity;


use ybrenLib\rocketmq\core\Column;

class SendResult extends Column
{
    protected $sendStatus = SendStatus::SEND_OK;
    protected $msgId;

    /**
     * @var MessageQueue
     */
    protected $messageQueue;
    protected $queueOffset;
    protected $transactionId;
    protected $offsetMsgId;
    protected $regionId;
    protected $traceOn = true;
    protected $brokerName;
    protected $msgKeys;

    /**
     * @return mixed
     */
    public function getSendStatus()
    {
        return $this->sendStatus;
    }

    /**
     * @param mixed $sendStatus
     */
    public function setSendStatus($sendStatus)
    {
        $this->sendStatus = $sendStatus;
    }

    /**
     * @return mixed
     */
    public function getMsgId()
    {
        return $this->msgId;
    }

    /**
     * @param mixed $msgId
     */
    public function setMsgId($msgId)
    {
        $this->msgId = $msgId;
    }

    /**
     * @return MessageQueue
     */
    public function getMessageQueue()
    {
        return $this->messageQueue;
    }

    /**
     * @param MessageQueue $messageQueue
     */
    public function setMessageQueue($messageQueue)
    {
        $this->messageQueue = $messageQueue;
    }

    /**
     * @return mixed
     */
    public function getQueueOffset()
    {
        return $this->queueOffset;
    }

    /**
     * @param mixed $queueOffset
     */
    public function setQueueOffset($queueOffset)
    {
        $this->queueOffset = $queueOffset;
    }

    /**
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param mixed $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * @return mixed
     */
    public function getOffsetMsgId()
    {
        return $this->offsetMsgId;
    }

    /**
     * @param mixed $offsetMsgId
     */
    public function setOffsetMsgId($offsetMsgId)
    {
        $this->offsetMsgId = $offsetMsgId;
    }

    /**
     * @return mixed
     */
    public function getRegionId()
    {
        return $this->regionId;
    }

    /**
     * @param mixed $regionId
     */
    public function setRegionId($regionId)
    {
        $this->regionId = $regionId;
    }

    /**
     * @return bool
     */
    public function isTraceOn()
    {
        return $this->traceOn;
    }

    /**
     * @param bool $traceOn
     */
    public function setTraceOn($traceOn)
    {
        $this->traceOn = $traceOn;
    }

    /**
     * @return mixed
     */
    public function getBrokerName()
    {
        return $this->brokerName;
    }

    /**
     * @param mixed $brokerName
     */
    public function setBrokerName($brokerName)
    {
        $this->brokerName = $brokerName;
    }

    /**
     * @return mixed
     */
    public function getMsgKeys()
    {
        return $this->msgKeys;
    }

    /**
     * @param mixed $msgKeys
     */
    public function setMsgKeys($msgKeys)
    {
        $this->msgKeys = $msgKeys;
    }
}