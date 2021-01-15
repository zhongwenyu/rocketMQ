<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\rocketmq\remoting\heartbeat\SubscriptionData;

interface MQConsumerInner
{
    function groupName();

    function messageModel();

    function consumeType();

    function consumeFromWhere();

    /**
     * @return SubscriptionData[]
     */
    function subscriptions();

    function doRebalance();

    function persistConsumerOffset();

    function updateTopicSubscribeInfo(string $topic, $info);

    /**
     * @return bool
     */
    function isSubscribeTopicNeedUpdate(string $topic);

    /**
     * @return bool
     */
    function isUnitMode();
}