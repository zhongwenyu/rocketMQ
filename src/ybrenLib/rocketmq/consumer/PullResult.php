<?php
namespace ybrenLib\rocketmq\consumer;

class PullResult
{
    protected $pullStatus;
    protected $nextBeginOffset;
    protected $minOffset;
    protected $maxOffset;
    protected $msgFoundList;

    /**
     * PullResult constructor.
     * @param $pullStatus
     * @param $nextBeginOffset
     * @param $minOffset
     * @param $maxOffset
     * @param $msgFoundList
     */
    public function __construct($pullStatus, $nextBeginOffset, $minOffset, $maxOffset, $msgFoundList)
    {
        $this->pullStatus = $pullStatus;
        $this->nextBeginOffset = $nextBeginOffset;
        $this->minOffset = $minOffset;
        $this->maxOffset = $maxOffset;
        $this->msgFoundList = $msgFoundList;
    }

    /**
     * @return mixed
     */
    public function getPullStatus()
    {
        return $this->pullStatus;
    }

    /**
     * @return mixed
     */
    public function getNextBeginOffset()
    {
        return $this->nextBeginOffset;
    }

    /**
     * @return mixed
     */
    public function getMinOffset()
    {
        return $this->minOffset;
    }

    /**
     * @return mixed
     */
    public function getMaxOffset()
    {
        return $this->maxOffset;
    }

    /**
     * @return mixed
     */
    public function getMsgFoundList()
    {
        return $this->msgFoundList;
    }

    /**
     * @param mixed $msgFoundList
     */
    public function setMsgFoundList($msgFoundList)
    {
        $this->msgFoundList = $msgFoundList;
    }
}