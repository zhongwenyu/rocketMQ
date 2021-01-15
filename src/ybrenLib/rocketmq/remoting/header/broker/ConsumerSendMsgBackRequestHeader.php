<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class ConsumerSendMsgBackRequestHeader extends Column implements CommandCustomHeader
{
    protected $offset;
    protected $group;
    protected $delayLevel;
    protected $originMsgId;
    protected $originTopic;
    protected $unitMode = false;
    protected $maxReconsumeTimes;

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
     * @param mixed $offset
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
    }

    /**
     * @param mixed $group
     */
    public function setGroup($group)
    {
        $this->group = $group;
    }

    /**
     * @param mixed $delayLevel
     */
    public function setDelayLevel($delayLevel)
    {
        $this->delayLevel = $delayLevel;
    }

    /**
     * @param mixed $originMsgId
     */
    public function setOriginMsgId($originMsgId)
    {
        $this->originMsgId = $originMsgId;
    }

    /**
     * @param mixed $originTopic
     */
    public function setOriginTopic($originTopic)
    {
        $this->originTopic = $originTopic;
    }

    /**
     * @param bool $unitMode
     */
    public function setUnitMode(bool $unitMode)
    {
        $this->unitMode = $unitMode;
    }

    /**
     * @param mixed $maxReconsumeTimes
     */
    public function setMaxReconsumeTimes($maxReconsumeTimes)
    {
        $this->maxReconsumeTimes = $maxReconsumeTimes;
    }
}