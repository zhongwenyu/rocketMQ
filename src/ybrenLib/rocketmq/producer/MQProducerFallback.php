<?php
namespace ybrenLib\rocketmq\producer;

use ybrenLib\rocketmq\entity\Message;
use ybrenLib\rocketmq\entity\SendResult;

interface MQProducerFallback
{
    function beforeSendMessage(Message $message);

    function afterSendMessage(Message $message , SendResult $sendResult);
}