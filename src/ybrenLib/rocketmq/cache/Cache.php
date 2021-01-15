<?php
namespace ybrenLib\rocketmq\cache;

use ybrenLib\rocketmq\entity\TopicRouteData;
use ybrenLib\rocketmq\producer\TransactionListener;

interface Cache
{
    function updateBroker(string $brokerName , array $brokerAddrs);

    function getBroker(string $brokerName);

    function addProducer($producerGroupName);

    /**
     * @return array
     */
    function getProducer();

    function clearAll();

    function addTransactionListener($producerGroup , $topicName , TransactionListener $transactionListener);

    /**
     * @param $producerGroup
     * @param $topicName
     * @return TransactionListener
     */
    function getTransactionListener($producerGroup , $topicName);

    /**
     * @param $topic
     * @return TopicRouteData|null
     */
    function getTopicRoute($topic);

    function getAllTopic();

    /**
     * @param $topic
     * @param TopicRouteData $topicRouteData
     */
    function updateTopicRoute($topic , TopicRouteData $topicRouteData);

    function isStopped();

    function setStopped();

    function rmStopped();
}