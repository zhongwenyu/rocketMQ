<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class PullMessageRequestHeader extends Column implements CommandCustomHeader
{
    protected $consumerGroup;
    
    protected $topic;
    
    protected $queueId;
    
    protected $queueOffset;
    
    protected $maxMsgNums;
    
    protected $sysFlag;
    
    protected $commitOffset;
    
    protected $suspendTimeoutMillis;

    protected $subscription;
    
    protected $subVersion;
    protected $expressionType;

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
     * @param mixed $consumerGroup
     */
    public function setConsumerGroup($consumerGroup)
    {
        $this->consumerGroup = $consumerGroup;
    }

    /**
     * @param mixed $topic
     */
    public function setTopic($topic)
    {
        $this->topic = $topic;
    }

    /**
     * @param mixed $queueId
     */
    public function setQueueId($queueId)
    {
        $this->queueId = $queueId;
    }

    /**
     * @param mixed $queueOffset
     */
    public function setQueueOffset($queueOffset)
    {
        $this->queueOffset = $queueOffset;
    }

    /**
     * @param mixed $maxMsgNums
     */
    public function setMaxMsgNums($maxMsgNums)
    {
        $this->maxMsgNums = $maxMsgNums;
    }

    /**
     * @param mixed $sysFlag
     */
    public function setSysFlag($sysFlag)
    {
        $this->sysFlag = $sysFlag;
    }

    /**
     * @param mixed $commitOffset
     */
    public function setCommitOffset($commitOffset)
    {
        $this->commitOffset = $commitOffset;
    }

    /**
     * @param mixed $suspendTimeoutMillis
     */
    public function setSuspendTimeoutMillis($suspendTimeoutMillis)
    {
        $this->suspendTimeoutMillis = $suspendTimeoutMillis;
    }

    /**
     * @param mixed $subscription
     */
    public function setSubscription($subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * @param mixed $subVersion
     */
    public function setSubVersion($subVersion)
    {
        $this->subVersion = $subVersion;
    }

    /**
     * @param mixed $expressionType
     */
    public function setExpressionType($expressionType)
    {
        $this->expressionType = $expressionType;
    }
}