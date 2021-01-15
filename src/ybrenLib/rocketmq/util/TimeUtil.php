<?php
namespace ybrenLib\rocketmq\util;


class TimeUtil
{
    public static function currentTimeMillis(){
        return intval(microtime(true)*1000);
    }
}