<?php
namespace ybrenLib\rocketmq\core;

class Lock
{
    private $lock;

    public function __construct(int $i = 1){
        $this->lock = new \Swoole\Atomic($i);
    }

    public function tryLock(int $timeout = -1){
        return $this->lock->wait($timeout);
    }

    public function release(){
        $this->lock->get() == 0 && $this->lock->wakeup();
    }
}