<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\entity\MessageQueue;

interface AllocateMessageQueueStrategy
{
    /**
     * @param string $consumerGroup
     * @param string $currentCID
     * @param MessageQueue[] $mqAll
     * @param string[] $cidAll
     * @return MessageQueue[]
     */
    function allocate(
        string $consumerGroup,
        string $currentCID,
        $mqAll,
        $cidAll
    );

    function getName();
}