<?php
namespace ybrenLib\rocketmq\entity;

class MessageExt extends Message
{
    protected $brokerName;
    protected $queueId;
    protected $storeSize;
    protected $queueOffset;
    // 消息系统flag，是否事务消息、是否压缩等
    protected $sysFlag;
    protected $bornTimestamp;
    // 消息发送者ip、端口号
    protected $bornHost;
    // 消息存储时间戳
    protected $storeTimestamp;
    // broker节点ip、端口号
    protected $storeHost;
    protected $msgId;
    protected $commitLogOffset;
    protected $bodyCRC;
    protected $reconsumeTimes;
    protected $preparedTransactionOffset;
    protected $tranStateTableOffset;

    public function __construct()
    {
        parent::__construct("", "");
    }

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
    public function getStoreSize()
    {
        return $this->storeSize;
    }

    /**
     * @param mixed $storeSize
     */
    public function setStoreSize($storeSize)
    {
        $this->storeSize = $storeSize;
    }

    /**
     * @return mixed
     */
    public function getQueueOffset()
    {
        return $this->queueOffset;
    }

    /**
     * @param mixed $queueOffset
     */
    public function setQueueOffset($queueOffset)
    {
        $this->queueOffset = $queueOffset;
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
    public function getBornHost()
    {
        return $this->bornHost;
    }

    /**
     * @param mixed $bornHost
     */
    public function setBornHost($bornHost)
    {
        $this->bornHost = $bornHost;
    }

    /**
     * @return mixed
     */
    public function getStoreTimestamp()
    {
        return $this->storeTimestamp;
    }

    /**
     * @param mixed $storeTimestamp
     */
    public function setStoreTimestamp($storeTimestamp)
    {
        $this->storeTimestamp = $storeTimestamp;
    }

    /**
     * @return mixed
     */
    public function getStoreHost()
    {
        return $this->storeHost;
    }

    /**
     * @param mixed $storeHost
     */
    public function setStoreHost($storeHost)
    {
        $this->storeHost = $storeHost;
    }

    /**
     * @return mixed
     */
    public function getMsgId()
    {
        return $this->msgId;
    }

    /**
     * @param mixed $msgId
     */
    public function setMsgId($msgId)
    {
        $this->msgId = $msgId;
    }

    /**
     * @return mixed
     */
    public function getCommitLogOffset()
    {
        return $this->commitLogOffset;
    }

    /**
     * @param mixed $commitLogOffset
     */
    public function setCommitLogOffset($commitLogOffset)
    {
        $this->commitLogOffset = $commitLogOffset;
    }

    /**
     * @return mixed
     */
    public function getBodyCRC()
    {
        return $this->bodyCRC;
    }

    /**
     * @param mixed $bodyCRC
     */
    public function setBodyCRC($bodyCRC)
    {
        $this->bodyCRC = $bodyCRC;
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
     * @return mixed
     */
    public function getPreparedTransactionOffset()
    {
        return $this->preparedTransactionOffset;
    }

    /**
     * @param mixed $preparedTransactionOffset
     */
    public function setPreparedTransactionOffset($preparedTransactionOffset)
    {
        $this->preparedTransactionOffset = $preparedTransactionOffset;
    }

    /**
     * @return mixed
     */
    public function getTranStateTableOffset()
    {
        return $this->tranStateTableOffset;
    }

    /**
     * @param mixed $tranStateTableOffset
     */
    public function setTranStateTableOffset($tranStateTableOffset)
    {
        $this->tranStateTableOffset = $tranStateTableOffset;
    }
}