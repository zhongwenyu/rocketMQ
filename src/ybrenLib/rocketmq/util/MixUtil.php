<?php
namespace ybrenLib\rocketmq\util;

use ybrenLib\rocketmq\core\AtomicLong;

class MixUtil
{
    public static $RETRY_GROUP_TOPIC_PREFIX = "%RETRY%";
    public static $CLIENT_INNER_PRODUCER_GROUP = "CLIENT_INNER_PRODUCER";

    public static function getRetryTopic(string $consumerGroup) {
        return self::$RETRY_GROUP_TOPIC_PREFIX . $consumerGroup;
    }

    public static function compareAndIncreaseOnly(AtomicLong $target, $value) {
        $prev = $target->get();
        while ($value > $prev) {
            $updated = $target->compareAndSet($prev, $value);
            if ($updated)
                return true;

            $prev = $target->get();
        }

        return false;
    }
}