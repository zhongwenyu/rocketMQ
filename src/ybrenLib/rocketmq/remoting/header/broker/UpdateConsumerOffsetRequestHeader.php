<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class UpdateConsumerOffsetRequestHeader extends Column implements CommandCustomHeader
{

    protected $consumerGroup;
    
    protected $topic;
    
    protected $queueId;
    
    protected $commitOffset;

    function getHeader()
    {
        $data = [];
        foreach ($this as $key => $value){
            if(!is_null($value)){
                $data[$key] = $value;
            }
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function getConsumerGroup()
    {
        return $this->consumerGroup;
    }

    /**
     * @param mixed $consumerGroup
     */
    public function setConsumerGroup($consumerGroup)
    {
        $this->consumerGroup = $consumerGroup;
    }

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

    /**
     * @return mixed
     */
    public function getQueueId()
    {
        return $this->queueId;
    }

    /**
     * @param mixed $queueId
     */
    public function setQueueId($queueId)
    {
        $this->queueId = $queueId;
    }

    /**
     * @return mixed
     */
    public function getCommitOffset()
    {
        return $this->commitOffset;
    }

    /**
     * @param mixed $commitOffset
     */
    public function setCommitOffset($commitOffset)
    {
        $this->commitOffset = $commitOffset;
    }
}