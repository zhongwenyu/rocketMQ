<?php
namespace ybrenLib\rocketmq\entity;

use ybrenLib\rocketmq\core\Column;

class QueueData extends Column
{
    protected $brokerName;
    protected $readQueueNums;
    protected $writeQueueNums;
    protected $perm;
    protected $topicSynFlag;

    /**
     * @return mixed
     */
    public function getBrokerName()
    {
        return $this->brokerName;
    }

    /**
     * @param mixed $brokerName
     */
    public function setBrokerName($brokerName)
    {
        $this->brokerName = $brokerName;
    }

    /**
     * @return mixed
     */
    public function getReadQueueNums()
    {
        return $this->readQueueNums;
    }

    /**
     * @param mixed $readQueueNums
     */
    public function setReadQueueNums($readQueueNums)
    {
        $this->readQueueNums = $readQueueNums;
    }

    /**
     * @return mixed
     */
    public function getWriteQueueNums()
    {
        return $this->writeQueueNums;
    }

    /**
     * @param mixed $writeQueueNums
     */
    public function setWriteQueueNums($writeQueueNums)
    {
        $this->writeQueueNums = $writeQueueNums;
    }

    /**
     * @return mixed
     */
    public function getPerm()
    {
        return $this->perm;
    }

    /**
     * @param mixed $perm
     */
    public function setPerm($perm)
    {
        $this->perm = $perm;
    }

    /**
     * @return mixed
     */
    public function getTopicSynFlag()
    {
        return $this->topicSynFlag;
    }

    /**
     * @param mixed $topicSynFlag
     */
    public function setTopicSynFlag($topicSynFlag)
    {
        $this->topicSynFlag = $topicSynFlag;
    }
}