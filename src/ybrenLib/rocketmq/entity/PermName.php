<?php
namespace ybrenLib\rocketmq\entity;

class PermName
{
    public static $PERM_PRIORITY = 0x1 << 3;
    public static $PERM_READ = 0x1 << 2;
    public static $PERM_WRITE = 0x1 << 1;
    public static $PERM_INHERIT = 0x1 << 0;

    public static function perm2String(int $perm) {
        $sb = "---";
        if (self::isReadable($perm)) {
            $sb = "R--";
        }

        if (self::isWriteable($perm)) {
            $sb = "-W-";
        }

        if (self::isInherited($perm)) {
            $sb = "--X";
        }

        return $sb;
    }

    public static function isReadable(int $perm) {
        return ($perm & self::$PERM_READ) == self::$PERM_READ;
    }

    public static function isWriteable(int $perm) {
        return ($perm & self::$PERM_WRITE) == self::$PERM_WRITE;
    }

    public static function isInherited(int $perm) {
        return ($perm & self::$PERM_INHERIT) == self::$PERM_INHERIT;
    }
}