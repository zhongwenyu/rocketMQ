<?php
namespace ybrenLib\rocketmq\entity;

class TransactionSendResult extends SendResult
{
    private $localTransactionState;

    /**
     * @return mixed
     */
    public function getLocalTransactionState()
    {
        return $this->localTransactionState;
    }

    /**
     * @param mixed $localTransactionState
     */
    public function setLocalTransactionState($localTransactionState)
    {
        $this->localTransactionState = $localTransactionState;
    }
}