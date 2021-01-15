<?php
namespace ybrenLib\rocketmq\core;

class Channel
{
    protected $channel;

    public function __construct($capacity = 1){
        $this->channel = new \Swoole\Coroutine\Channel($capacity);
    }

    public function push($data , $timeout = -1){
        return $this->channel->push($data , $timeout);
    }

    public function pop($timeout = -1){
        return $this->channel->pop($timeout);
    }

    public function close(){
        $this->channel->close();
    }

    /**
     * @return int
     */
    public function length(){
        return $this->channel->length();
    }

    public function errCode(){
        return $this->channel->errCode;
    }
}