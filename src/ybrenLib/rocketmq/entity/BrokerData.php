<?php
namespace ybrenLib\rocketmq\entity;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\MQConstants;

class BrokerData extends Column
{
    protected $cluster;
    protected $brokerName;
    protected $brokerAddrs = [];
    protected $random;

    public function selectBrokerAddr() {
        $addr = $this->brokerAddrs[MQConstants::MASTER_ID] ?? null;

        if ($addr == null) {
            return $this->brokerAddrs[rand(0 , (count($this->brokerAddrs) - 1))];
        }

        return $addr;
    }

    /**
     * @return mixed
     */
    public function getCluster()
    {
        return $this->cluster;
    }

    /**
     * @param mixed $cluster
     */
    public function setCluster($cluster)
    {
        $this->cluster = $cluster;
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
     * @return array
     */
    public function getBrokerAddrs()
    {
        return $this->brokerAddrs;
    }

    /**
     * @param array $brokerAddrs
     */
    public function setBrokerAddrs($brokerAddrs)
    {
        $this->brokerAddrs = $brokerAddrs;
    }

    /**
     * @return mixed
     */
    public function getRandom()
    {
        return $this->random;
    }

    /**
     * @param mixed $random
     */
    public function setRandom($random)
    {
        $this->random = $random;
    }
}