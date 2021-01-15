<?php
namespace ybrenLib\rocketmq\remoting\heartbeat;


use ybrenLib\rocketmq\core\Column;

class HeartbeatData extends Column
{
    protected $clientID;
    /**
     * @var ProducerData[]
     */
    protected $producerDataSet = [];
    /**
     * @var ConsumerData[]
     */
    protected $consumerDataSet = [];

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
     * @return ProducerData[]
     */
    public function getProducerDataSet(): array
    {
        return $this->producerDataSet;
    }

    /**
     * @param ProducerData[] $producerDataSet
     */
    public function setProducerDataSet(array $producerDataSet)
    {
        $this->producerDataSet = $producerDataSet;
    }

    /**
     * @return ConsumerData[]
     */
    public function getConsumerDataSet(): array
    {
        return $this->consumerDataSet;
    }

    /**
     * @param ConsumerData[] $consumerDataSet
     */
    public function setConsumerDataSet(array $consumerDataSet)
    {
        $this->consumerDataSet = $consumerDataSet;
    }
}