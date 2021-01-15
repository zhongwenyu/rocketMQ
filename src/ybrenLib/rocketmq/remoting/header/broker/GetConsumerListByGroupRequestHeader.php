<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class GetConsumerListByGroupRequestHeader implements CommandCustomHeader
{
    private $consumerGroup;

    /**
     * GetConsumerListByGroupRequestHeader constructor.
     * @param $consumerGroup
     */
    public function __construct($consumerGroup)
    {
        $this->consumerGroup = $consumerGroup;
    }

    function getHeader()
    {
        $data = [];
        if(!empty($this->consumerGroup)){
            $data["consumerGroup"] = $this->consumerGroup;
        }
        return $data;
    }

}