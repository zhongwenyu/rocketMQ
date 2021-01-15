<?php
namespace ybrenLib\rocketmq\producer;

use ybrenLib\rocketmq\entity\Message;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\entity\SendResult;

interface TransactionListener
{

    /**
     * 执行本地事务
     * @param Message $msg
     * @param SendResult $sendResult
     * @param $arg
     * @return mixed
     */
    function executeLocalTransaction(Message $msg , SendResult $sendResult, $arg);

    /**
     * 回查事务状态
     * @param MessageExt $msg
     * @return LocalTransactionState
     */
    function checkLocalTransaction(MessageExt $msg);
}