<?php
namespace ybrenLib\rocketmq\remoting\body;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\entity\MessageQueue;

class LockBatchRequestBody extends Column
{
    protected $consumerGroup;
    protected $clientId;
    protected $mqSet = [];

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
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * @param mixed $clientId
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * @return array
     */
    public function getMqSet(): array
    {
        return $this->mqSet;
    }

    /**
     * @param array $mqSet
     */
    public function setMqSet(array $mqSet)
    {
        $this->mqSet = $mqSet;
    }

    /**
     * @param MessageQueue $me
     */
    public function addMq(MessageQueue $me)
    {
        $this->mqSet[] = $me;
    }
}