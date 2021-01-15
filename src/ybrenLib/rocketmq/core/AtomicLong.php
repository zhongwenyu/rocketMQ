<?php
namespace ybrenLib\rocketmq\core;

class AtomicLong
{
    private $atomic;

    public function __construct($value = 0){
        $this->atomic = new \Swoole\Atomic($value);
    }

    public function getAndIncrement($value = 1){
        return $this->atomic->add($value);
    }

    public function getAndDecrement($value = 1){
        return $this->atomic->sub($value);
    }

    public function get(){
        return $this->atomic->get();
    }

    public function set($value){
        $this->atomic->set($value);
    }

    public function compareAndSet($cmpvalue , $setvalue){
        return $this->atomic->cmpset($cmpvalue , $setvalue);
    }
}