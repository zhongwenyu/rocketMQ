<?php
namespace ybrenLib\rocketmq\consumer\listener;

class ConsumeOrderlyStatus
{
    const SUCCESS = "SUCCESS";

    const ROLLBACK = "ROLLBACK";

    const COMMIT = "COMMIT";

    const SUSPEND_CURRENT_QUEUE_A_MOMENT = "SUSPEND_CURRENT_QUEUE_A_MOMENT";
}