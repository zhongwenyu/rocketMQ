<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\consumer\listener\ConsumeOrderlyContext;
use ybrenLib\rocketmq\consumer\listener\ConsumeOrderlyStatus;
use ybrenLib\rocketmq\entity\MessageExt;

interface MessageListenerOrderly extends MessageListener
{
    /**
     * @param MessageExt[] $msgs
     * @param ConsumeOrderlyContext $context
     * @return ConsumeOrderlyStatus
     */
    function consumeMessage(array $msgs, ConsumeOrderlyContext $context);
}