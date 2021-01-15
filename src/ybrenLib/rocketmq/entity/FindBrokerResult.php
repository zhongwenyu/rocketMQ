<?php
namespace ybrenLib\rocketmq\entity;

use ybrenLib\rocketmq\core\Column;

class FindBrokerResult extends Column
{
    protected $brokerAddr;
    /**
     * @var bool
     */
    protected $slave;
    protected $brokerVersion;

    /**
     * FindBrokerResult constructor.
     * @param $brokerAddr
     * @param bool $slave
     */
    public function __construct($brokerAddr, bool $slave)
    {
        $this->brokerAddr = $brokerAddr;
        $this->slave = $slave;
    }

    /**
     * @return mixed
     */
    public function getBrokerAddr()
    {
        return $this->brokerAddr;
    }

    /**
     * @return bool
     */
    public function isSlave(): bool
    {
        return $this->slave;
    }

    /**
     * @return mixed
     */
    public function getBrokerVersion()
    {
        return $this->brokerVersion;
    }


}