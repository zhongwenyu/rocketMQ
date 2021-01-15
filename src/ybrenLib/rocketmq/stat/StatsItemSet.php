<?php
namespace ybrenLib\rocketmq\stat;

use ybrenLib\rocketmq\core\ConcurrentMap;

class StatsItemSet
{
    private $statsItemTable;

    private $statsName;
    private $scheduledExecutorService;

    public function __construct(){
        $this->statsItemTable = new ConcurrentMap();
    }

    public function addRTValue(string $statsKey, $incValue, $incTimes) {
        $statsItem = this.getAndCreateRTStatsItem(statsKey);
        statsItem.getValue().addAndGet(incValue);
        statsItem.getTimes().addAndGet(incTimes);
    }

    public function getAndCreateRTStatsItem(string $statsKey) {
        return $this->getAndCreateItem($statsKey, true);
    }

    public function getAndCreateItem(string $statsKey, bool $rtItem) {
        $statsItem = $this->statsItemTable->get($statsKey);
        if (null == $statsItem) {
            if ($rtItem) {
                $statsItem = new RTStatsItem(this.statsName, statsKey, this.scheduledExecutorService, this.log);
            } else {
    statsItem = new StatsItem(this.statsName, statsKey, this.scheduledExecutorService, this.log);
}
StatsItem prev = this.statsItemTable.putIfAbsent(statsKey, statsItem);

            if (null != prev) {
                statsItem = prev;
                // statsItem.init();
            }
        }

        return statsItem;
    }
}