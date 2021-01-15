<?php
namespace ybrenLib\rocketmq\strategy;

class LatencyFaultTolerance
{
    /**
     * @var FaultItem[]
     */
    private $faultItemTable = [];

    // 判断该broker，即broker是否在不可用的列表里
    public function isAvailable($name) {
        $faultItem = $this->faultItemTable[$name] ?? null;
        if ($faultItem != null) {
            return $faultItem->isAvailable();
        }
        return true;
    }
}