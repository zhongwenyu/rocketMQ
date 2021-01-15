<?php
namespace ybrenLib\rocketmq\producer;

class LocalTransactionState
{
    const COMMIT_MESSAGE = "COMMIT_MESSAGE";

    const ROLLBACK_MESSAGE = "ROLLBACK_MESSAGE";

    const UNKNOW = "UNKNOW";
}