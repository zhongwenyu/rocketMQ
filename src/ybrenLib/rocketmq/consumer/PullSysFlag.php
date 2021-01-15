<?php
namespace ybrenLib\rocketmq\consumer;

class PullSysFlag
{
    // 表示从内存中读取到的消息进度大于0
    private static $FLAG_COMMIT_OFFSET = 0x1;
    // 表示消息拉取时支持挂起
    private static $FLAG_SUSPEND = 0x1 << 1;
    // 消息过滤机制为表达式
    private static $FLAG_SUBSCRIPTION = 0x1 << 2;
    // 消息过滤机制为类过滤模式
    private static $FLAG_CLASS_FILTER = 0x1 << 3;
    private static $FLAG_LITE_PULL_MESSAGE = 0x1 << 4;

    public static function buildSysFlag(bool $commitOffset, bool $suspend,
                                        bool $subscription, bool $classFilter) {
        $flag = 0;

        if ($commitOffset) {
            $flag |= self::$FLAG_COMMIT_OFFSET;
        }

        if ($suspend) {
            $flag |= self::$FLAG_SUSPEND;
        }

        if ($subscription) {
            $flag |= self::$FLAG_SUBSCRIPTION;
        }

        if ($classFilter) {
            $flag |= self::$FLAG_CLASS_FILTER;
        }

        return $flag;
    }

    public static function clearCommitOffsetFlag(int $sysFlag) {
        return $sysFlag & (~self::$FLAG_COMMIT_OFFSET);
    }

    public static function hasClassFilterFlag(int $sysFlag) {
        return ($sysFlag & self::$FLAG_CLASS_FILTER) == self::$FLAG_CLASS_FILTER;
    }
}