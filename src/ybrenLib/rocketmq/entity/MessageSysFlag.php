<?php
namespace ybrenLib\rocketmq\entity;


class MessageSysFlag
{
    const DEFAULT = 0;
    const PREPARE_TRANS_MSG = 4;
    const TRANSACTION_COMMIT_TYPE = 8;
    const TRANSACTION_ROLLBACK_TYPE = 12;
    const TRANSACTION_NOT_TYPE = 0;
}