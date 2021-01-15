<?php
namespace ybrenLib\rocketmq\util;

class ScheduleTaskUtil
{
    /**
     * @param $interval
     * @param \Closure $closure
     */
    public static function timer($interval , \Closure $closure){
        \swoole_timer_tick($interval , $closure);
    }

    /**
     * @param $delay
     * @param \Closure $closure
     */
    public static function after($delay , \Closure $closure){
        \swoole_timer_after($delay , $closure);
    }

    /**
     * @param $timerId
     * @return bool
     */
    public static function stop($timerId){
        return \swoole_timer_clear($timerId);
    }

    /**
     * @return bool
     */
    public static function stopAll(){
        return \Swoole\Timer::clearAll();
    }
}