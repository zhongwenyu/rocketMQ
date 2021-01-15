<?php
namespace ybrenLib\rocketmq\entity;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\core\Lock;

class MessageQueue extends Column
{
    protected $topic;
    protected $brokerName;
    protected $queueId;
    /**
     * 消费锁
     * @var Lock
     */
    private $consumeLock;

    /**
     * MessageQueue constructor.
     * @param $topic
     * @param $brokerName
     * @param $queueId
     */
    public function __construct($topic, $brokerName, $queueId)
    {
        $this->topic = $topic;
        $this->brokerName = $brokerName;
        $this->queueId = $queueId;
        $this->consumeLock = new Lock();
    }

    /**
     * @return mixed
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * @param mixed $topic
     */
    public function setTopic($topic)
    {
        $this->topic = $topic;
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
    public function getQueueId()
    {
        return $this->queueId;
    }

    /**
     * @param mixed $queueId
     */
    public function setQueueId($queueId)
    {
        $this->queueId = $queueId;
    }

    /**
     * @return Lock
     */
    public function getConsumeLock(): Lock
    {
        return $this->consumeLock;
    }

    public function hashCode(){
        return (is_null($this->topic) ? "" : $this->topic) . (is_null($this->brokerName) ? "" : $this->brokerName) . (is_null($this->queueId) ? "" : $this->queueId);
    }
}