<?php
namespace ybrenLib\rocketmq\consumer\store;

use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\MQAsyncClientInstance;

class LocalFileOffsetStore implements OffsetStore
{
    /**
     * @var MQAsyncClientInstance
     */
    private $mqClientFactory;

    /**
     * @var string
     */
    private $consumerGroup;

    /**
     * LocalFileOffsetStore constructor.
     * @param MQAsyncClientInstance $mqClientFactory
     * @param string $consumerGroup
     */
    public function __construct(MQAsyncClientInstance $mqClientFactory, string $consumerGroup)
    {
        $this->mqClientFactory = $mqClientFactory;
        $this->consumerGroup = $consumerGroup;
    }

    function load()
    {
        // TODO: Implement load() method.
    }

    function updateOffset(MessageQueue $mq, $offset, bool $increaseOnly)
    {
        // TODO: Implement updateOffset() method.
    }

    function readOffset(MessageQueue $mq, $type)
    {
        // TODO: Implement readOffset() method.
    }

    function persistAll($mqs)
    {
        // TODO: Implement persistAll() method.
    }

    function persist(MessageQueue $mq)
    {
        // TODO: Implement persist() method.
    }

    function removeOffset(MessageQueue $mq)
    {
        // TODO: Implement removeOffset() method.
    }

    function cloneOffsetTable(string $topic)
    {
        // TODO: Implement cloneOffsetTable() method.
    }

    function updateConsumeOffsetToBroker(MessageQueue $mq, int $offset, bool $isOneway)
    {
        // TODO: Implement updateConsumeOffsetToBroker() method.
    }

}