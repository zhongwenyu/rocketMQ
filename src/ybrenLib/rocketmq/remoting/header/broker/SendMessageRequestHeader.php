<?php
namespace ybrenLib\rocketmq\remoting\header\broker;

use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class SendMessageRequestHeader implements CommandCustomHeader
{
    private $producerGroup;
    private $topic;
    private $defaultTopic;
    private $defaultTopicQueueNums;
    private $queueId;
    private $sysFlag;
    private $bornTimestamp;
    private $flag;
    private $properties;
    private $reconsumeTimes;
    private $unitMode = false;
    private $batch = false;
    private $maxReconsumeTimes;

    function getHeader()
    {
        $data = [];
        if(!is_null($this->producerGroup)){
            $data['producerGroup'] = $this->producerGroup;
        }
        if(!is_null($this->topic)){
            $data['topic'] = $this->topic;
        }
        if(!is_null($this->defaultTopic)){
            $data['defaultTopic'] = $this->defaultTopic;
        }
        if(!is_null($this->defaultTopicQueueNums)){
            $data['defaultTopicQueueNums'] = $this->defaultTopicQueueNums;
        }
        if(!is_null($this->queueId)){
            $data['queueId'] = $this->queueId;
        }
        if(!is_null($this->sysFlag)){
            $data['sysFlag'] = $this->sysFlag;
        }
        if(!is_null($this->bornTimestamp)){
            $data['bornTimestamp'] = $this->bornTimestamp;
        }
        if(!is_null($this->flag)){
            $data['flag'] = $this->flag;
        }
        if(!is_null($this->properties)){
            $data['properties'] = $this->properties;
        }
        if(!is_null($this->reconsumeTimes)){
            $data['reconsumeTimes'] = $this->reconsumeTimes;
        }
        if(!is_null($this->unitMode)){
            $data['unitMode'] = $this->unitMode;
        }
        if(!is_null($this->batch)){
            $data['batch'] = $this->batch;
        }
        if(!is_null($this->maxReconsumeTimes)){
            $data['maxReconsumeTimes'] = $this->maxReconsumeTimes;
        }
        return $data;
    }

    /**
     * @return mixed
     */
    public function getProducerGroup()
    {
        return $this->producerGroup;
    }

    /**
     * @param mixed $producerGroup
     */
    public function setProducerGroup($producerGroup)
    {
        $this->producerGroup = $producerGroup;
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
    public function getDefaultTopic()
    {
        return $this->defaultTopic;
    }

    /**
     * @param mixed $defaultTopic
     */
    public function setDefaultTopic($defaultTopic)
    {
        $this->defaultTopic = $defaultTopic;
    }

    /**
     * @return mixed
     */
    public function getDefaultTopicQueueNums()
    {
        return $this->defaultTopicQueueNums;
    }

    /**
     * @param mixed $defaultTopicQueueNums
     */
    public function setDefaultTopicQueueNums($defaultTopicQueueNums)
    {
        $this->defaultTopicQueueNums = $defaultTopicQueueNums;
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
    public function getSysFlag()
    {
        return $this->sysFlag;
    }

    /**
     * @param mixed $sysFlag
     */
    public function setSysFlag($sysFlag)
    {
        $this->sysFlag = $sysFlag;
    }

    /**
     * @return mixed
     */
    public function getBornTimestamp()
    {
        return $this->bornTimestamp;
    }

    /**
     * @param mixed $bornTimestamp
     */
    public function setBornTimestamp($bornTimestamp)
    {
        $this->bornTimestamp = $bornTimestamp;
    }

    /**
     * @return mixed
     */
    public function getFlag()
    {
        return $this->flag;
    }

    /**
     * @param mixed $flag
     */
    public function setFlag($flag)
    {
        $this->flag = $flag;
    }

    /**
     * @return mixed
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param mixed $properties
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
    }

    /**
     * @return mixed
     */
    public function getReconsumeTimes()
    {
        return $this->reconsumeTimes;
    }

    /**
     * @param mixed $reconsumeTimes
     */
    public function setReconsumeTimes($reconsumeTimes)
    {
        $this->reconsumeTimes = $reconsumeTimes;
    }

    /**
     * @return bool
     */
    public function isUnitMode()
    {
        return $this->unitMode;
    }

    /**
     * @param bool $unitMode
     */
    public function setUnitMode($unitMode)
    {
        $this->unitMode = $unitMode;
    }

    /**
     * @return bool
     */
    public function isBatch()
    {
        return $this->batch;
    }

    /**
     * @param bool $batch
     */
    public function setBatch($batch)
    {
        $this->batch = $batch;
    }

    /**
     * @return mixed
     */
    public function getMaxReconsumeTimes()
    {
        return $this->maxReconsumeTimes;
    }

    /**
     * @param mixed $maxReconsumeTimes
     */
    public function setMaxReconsumeTimes($maxReconsumeTimes)
    {
        $this->maxReconsumeTimes = $maxReconsumeTimes;
    }
}