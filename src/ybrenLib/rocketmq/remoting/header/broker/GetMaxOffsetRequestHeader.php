<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class GetMaxOffsetRequestHeader implements CommandCustomHeader
{

    private $topic;

    private $queueId;

    /**
     * GetMaxOffsetRequestHeader constructor.
     * @param $topic
     * @param $queueId
     */
    public function __construct($topic, $queueId)
    {
        $this->topic = $topic;
        $this->queueId = $queueId;
    }


    function getHeader()
    {
        $data = [];
        if(!is_null($this->topic)){
            $data["topic"] = $this->topic;
        }
        if(!is_null($this->queueId)){
            $data["queueId"] = $this->queueId;
        }
        return $data;
    }


}