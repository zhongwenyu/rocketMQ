<?php
namespace ybrenLib\rocketmq\core;

class ConcurrentMap extends ObjectMap
{
    private $yac;

    public function __construct(){
        $this->yac = new \Yac(uniqid());
    }

    public function putIfAbsent($offset , $value , \Closure $callback = null){
        $key = $this->getKey($offset);
        if(!$this->offsetExists($offset) && $this->yac->add(md5($key) , 1, 1)){
            if($value == null && $callback != null){
                $callback($value);
            }
            $this->offsetSet($offset , $value);
            return null;
        }else{
            while (!$this->offsetExists($offset)){
                usleep(1);
            }
            return $this->offsetGet($offset);
        }
    }
}