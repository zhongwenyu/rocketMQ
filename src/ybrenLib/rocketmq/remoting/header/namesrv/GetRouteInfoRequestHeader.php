<?php
namespace ybrenLib\rocketmq\remoting\header\namesrv;

use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class GetRouteInfoRequestHeader implements CommandCustomHeader
{
    private $topic;

    /**
     * @return mixed
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * @param mixed $topic
     */
    public function setTopic($topic)
    {
        $this->topic = $topic;
    }

    function getHeader()
    {
        return [
            "topic" => $this->topic
        ];
    }
}