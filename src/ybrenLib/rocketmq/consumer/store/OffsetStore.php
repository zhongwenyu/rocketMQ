<?php
namespace ybrenLib\rocketmq\consumer\store;

use ybrenLib\rocketmq\entity\MessageQueue;

interface OffsetStore
{
    /**
     * Load
     */
    function load();

    /**
     * Update the offset,store it in memory
     * @param MessageQueue $mq
     * @param $offset
     * @param bool $increaseOnly
     * @return mixed
     */
    function updateOffset(MessageQueue $mq, $offset, bool $increaseOnly);

    /**
     * Get offset from local storage
     * @param MessageQueue $mq
     * @param $type
     * @return mixed
     */
    function readOffset(MessageQueue $mq, $type);

    /**
     * Persist all offsets,may be in local storage or remote name server
     * @param MessageQueue[] $mqs
     * @return mixed
     */
    function persistAll($mqs);

    /**
     * Persist the offset,may be in local storage or remote name server
     * @param MessageQueue $mq
     * @return mixed
     */
    function persist(MessageQueue $mq);

    /**
     * @param MessageQueue $mq
     * @return mixed
     */
    function removeOffset(MessageQueue $mq);

    /**
     * @param string $topic
     * @return mixed
     */
    function cloneOffsetTable(string $topic);

    /**
     * @param MessageQueue $mq
     * @param int $offset
     * @param bool $isOneway
     * @return mixed
     */
    function updateConsumeOffsetToBroker(MessageQueue $mq, int $offset, bool $isOneway);
}