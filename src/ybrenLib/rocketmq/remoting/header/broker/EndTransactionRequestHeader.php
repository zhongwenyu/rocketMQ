<?php


namespace ybrenLib\rocketmq\remoting\header\broker;


use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class EndTransactionRequestHeader implements CommandCustomHeader
{
    protected $producerGroup;

    protected $tranStateTableOffset;

    protected $commitLogOffset;

    protected $commitOrRollback; 
    // TRANSACTION_COMMIT_TYPE
    // TRANSACTION_ROLLBACK_TYPE
    // TRANSACTION_NOT_TYPE
    
    protected $fromTransactionCheck = false;
    
    protected $msgId;

    protected $transactionId;

    function getHeader()
    {
        $data = [];
        if(!is_null($this->producerGroup)){
            $data['producerGroup'] = $this->producerGroup;
        }
        if(!is_null($this->tranStateTableOffset)){
            $data['tranStateTableOffset'] = $this->tranStateTableOffset;
        }
        if(!is_null($this->commitLogOffset)){
            $data['commitLogOffset'] = $this->commitLogOffset;
        }
        if(!is_null($this->commitOrRollback)){
            $data['commitOrRollback'] = $this->commitOrRollback;
        }
        if(!is_null($this->fromTransactionCheck)){
            $data['fromTransactionCheck'] = $this->fromTransactionCheck;
        }
        if(!is_null($this->msgId)){
            $data['msgId'] = $this->msgId;
        }
        if(!is_null($this->transactionId)){
            $data['transactionId'] = $this->transactionId;
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
    public function getCommitOrRollback()
    {
        return $this->commitOrRollback;
    }

    /**
     * @param mixed $commitOrRollback
     */
    public function setCommitOrRollback($commitOrRollback)
    {
        $this->commitOrRollback = $commitOrRollback;
    }

    /**
     * @return bool
     */
    public function isFromTransactionCheck(): bool
    {
        return $this->fromTransactionCheck;
    }

    /**
     * @param bool $fromTransactionCheck
     */
    public function setFromTransactionCheck(bool $fromTransactionCheck)
    {
        $this->fromTransactionCheck = $fromTransactionCheck;
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
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param mixed $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
    }
}