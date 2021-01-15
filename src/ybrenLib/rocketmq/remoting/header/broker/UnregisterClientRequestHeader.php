<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class UnregisterClientRequestHeader implements CommandCustomHeader
{
    private $clientID;
    private $producerGroup;
    private $consumerGroup;

    function getHeader()
    {
        $data = [];
        if(!is_null($this->clientID)){
            $data['clientID'] = $this->clientID;
        }
        if(!is_null($this->producerGroup)){
            $data['producerGroup'] = $this->producerGroup;
        }
        if(!is_null($this->consumerGroup)){
            $data['consumerGroup'] = $this->consumerGroup;
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function getClientID()
    {
        return $this->clientID;
    }

    /**
     * @param mixed $clientID
     */
    public function setClientID($clientID)
    {
        $this->clientID = $clientID;
    }

    /**
     * @return mixed
     */
    public function getProducerGroup()
    {
        return $this->producerGroup;
    }

    /**
     * @param mixed $producerGroup
     */
    public function setProducerGroup($producerGroup)
    {
        $this->producerGroup = $producerGroup;
    }

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
}