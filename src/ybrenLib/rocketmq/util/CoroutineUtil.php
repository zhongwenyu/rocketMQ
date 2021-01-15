<?php
namespace ybrenLib\rocketmq\util;

class CoroutineUtil
{
    /**
     * 协程处理任务
     * @param \Closure $callback
     */
    public static function run(\Closure $callback){
        \Swoole\Coroutine::create($callback);
    }
}