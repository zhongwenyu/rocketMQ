<?php
namespace ybrenLib\rocketmq\strategy;

use ybrenLib\rocketmq\util\TimeUtil;

class FaultItem
{
    // 唯一值，这里是brokerName
    private $name;
    // 本次消息发送延迟
    private $currentLatency;
    // 故障规避开始时间
    private $startTimestamp;

    public function __construct($name) {
        $this->name = $name;
    }

    public function isAvailable() {
        return (TimeUtil::currentTimeMillis() - $this->startTimestamp) >= 0;
    }
}