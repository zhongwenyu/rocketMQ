<?php
namespace ybrenLib\rocketmq\consumer\rebalance;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\consumer\AllocateMessageQueueStrategy;

class AllocateMessageQueueAveragely implements AllocateMessageQueueStrategy
{
    /**
     * @var Logger
     */
    private $log;
    
    public function __construct()
    {
        $this->log = LoggerFactory::getLogger(AllocateMessageQueueAveragely::class);
    }

    public function allocate(string $consumerGroup, string $currentCID, $mqAll, $cidAll)
    {
        if (empty($currentCID)) {
            throw new \Exception("currentCID is empty");
        }
        if (empty($mqAll)) {
            throw new \Exception("mqAll is null or mqAll empty");
        }
        if (empty($cidAll)) {
            throw new \Exception("cidAll is null or cidAll empty");
        }

        $result = [];
        if (!in_array($currentCID , $cidAll)) {
            $this->log->info("[BUG] ConsumerGroup: {} The consumerId: {} not in cidAll: {}",
                $consumerGroup,
                $currentCID,
                json_encode($cidAll));
            return $result;
        }

        $mqAllSize = count($mqAll);
        $cidSize = count($cidAll);
        $index = array_search($currentCID , $cidAll);
        $mod = $mqAllSize % $cidSize;
        $averageSize =
            $mqAllSize <= $cidSize ? 1 : ($mod > 0 && $index < $mod ? $mqAllSize / $cidSize
                + 1 : $mqAllSize / $cidSize);
        $startIndex = ($mod > 0 && $index < $mod) ? $index * $averageSize : $index * $averageSize + $mod;
        $range = min($averageSize, $mqAllSize - $startIndex);
        for ($i = 0; $i < $range; $i++) {
            $result[] = $mqAll[($startIndex + $i) % $mqAllSize];
        }
        return $result;
    }

    public function getName()
    {
        return "AVG";
    }

}