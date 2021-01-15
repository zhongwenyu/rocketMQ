<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\core\Channel;

class ConsumeMessageChannel extends Channel
{
    public function __construct($capacity = 1){
        parent::__construct($capacity);
    }

    public function pushMessage(ConsumeRequest $consumeRequest){
        return $this->push($consumeRequest);
    }

    /**
     * @return ConsumeRequest
     */
    public function popMessage(){
        return $this->pop();
    }
}