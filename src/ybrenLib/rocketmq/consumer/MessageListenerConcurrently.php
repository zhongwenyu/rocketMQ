<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\consumer\listener\ConsumeConcurrentlyContext;
use ybrenLib\rocketmq\consumer\listener\ConsumeConcurrentlyStatus;
use ybrenLib\rocketmq\entity\MessageExt;

interface MessageListenerConcurrently extends MessageListener
{
    /**
     * @param MessageExt[] $msgs
     * @param ConsumeConcurrentlyContext $context
     * @return string
     */
    function consumeMessage(array $msgs, ConsumeConcurrentlyContext $context);
}