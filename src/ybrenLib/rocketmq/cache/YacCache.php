<?php
namespace ybrenLib\rocketmq\cache;

use ybrenLib\logger\LoggerConfig;
use ybrenLib\logger\utils\ConfigUtil;
use ybrenLib\rocketmq\entity\TopicRouteData;
use ybrenLib\rocketmq\producer\TransactionListener;

class YacCache implements Cache
{
    private $yac;
    private $topicRouteKeyPrefix = "rocketmq:topicroute:";
    private $allTopicRouteKey = "rocketmq:alltopic";
    private $producerGroupKey = "rocketmq:producergroup";
    private $consumerGroupKey = "rocketmq:consumergroup";
    private $brokerKeyPrefix = "rocketmq:brokername";
    private $clientStopKeyPrefix = "rocketmq:clientStop:";
    private $MQAsyncClientInstanceKey = "MQAsyncClientInstance";
    private $expire = 30;

    public function __construct(){
        $this->yac = new \Yac();
    }

    function updateBroker(string $brokerName , array $brokerAddrs)
    {
        $this->yac->set($this->brokerKeyPrefix . $brokerName , $brokerAddrs);
    }

    function getBroker(string $brokerName)
    {
        return $this->yac->get($this->brokerKeyPrefix . $brokerName);
    }

    public function addProducer($producerGroupName)
    {
        $producerGroup = $this->yac->get($this->getProducerGroupKey());
        empty($producerGroup) && $producerGroup = [];
        if(!in_array($producerGroupName , $producerGroup)){
            $producerGroup[] = $producerGroupName;
            $this->yac->set($this->getProducerGroupKey() , $producerGroup);
        }
    }

    public function getProducer()
    {
        $producerGroupYacData = $this->yac->get($this->getProducerGroupKey());
        return empty($producerGroupYacData) ? [] : $producerGroupYacData;
    }

    public function clearAll()
    {
        $this->yac->set($this->getProducerGroupKey() , "");
        $topics = $this->yac->get($this->allTopicRouteKey);
        if(!empty($topics)){
            foreach ($topics as $topic){
                $key = md5($this->topicRouteKeyPrefix.$topic);
                $this->yac->delete($key);
            }
        }
        $this->yac->delete($this->allTopicRouteKey);
        $this->yac->delete($this->MQAsyncClientInstanceKey);
    }

    public function addTransactionListener($producerGroup, $topicName, TransactionListener $transactionListener)
    {
        $key = md5("rocketmq:transactionlistener:" .$producerGroup. ":" . $topicName);
        $this->yac->set($key , $transactionListener);
    }

    public function getTransactionListener($producerGroup, $topicName)
    {
        $key = md5("rocketmq:transactionlistener:" .$producerGroup. ":" . $topicName);
        return $this->yac->get($key);
    }

    public function getTopicRoute($topic)
    {
        $key = md5($this->topicRouteKeyPrefix.$topic);
        return $this->yac->get($key);
    }

    public function getAllTopic(){
        return $this->yac->get($this->allTopicRouteKey);
    }

    public function updateTopicRoute($topic, TopicRouteData $topicRouteData)
    {
        $topicRouteKey = md5($this->topicRouteKeyPrefix.$topic);

        // 更新topic路由信息缓存
        $this->yac->set($topicRouteKey , $topicRouteData , $this->expire);

        // 更新broker
        $brokerData = $topicRouteData->getBrokerDatas();
        if(!empty($brokerData)){
            foreach ($brokerData as $brokerInfo){
                $brokerAddrs = $brokerInfo->getBrokerAddrs();
                if(!empty($brokerAddrs)){
                    $this->updateBroker($brokerInfo->getBrokerName() , $brokerAddrs);
                }
            }
        }

        // 更新所有topic
        $alltopic = $this->yac->get($this->allTopicRouteKey);
        empty($alltopic) && $alltopic = [];
        if(!in_array($topic , $alltopic)){
            $alltopic[] = $topic;
            $this->yac->set($this->allTopicRouteKey , $alltopic);
        }
    }

    function isStopped()
    {
        $appName = ConfigUtil::getAppName(LoggerConfig::getConfig());
        $isStop = $this->yac->get($this->clientStopKeyPrefix . $appName);
        return empty($isStop) ? false : true;
    }

    function setStopped()
    {
        $appName = ConfigUtil::getAppName(LoggerConfig::getConfig());
        $this->yac->set($this->clientStopKeyPrefix . $appName , "1");
    }

    function rmStopped()
    {
        $appName = ConfigUtil::getAppName(LoggerConfig::getConfig());
        $this->yac->delete($this->clientStopKeyPrefix . $appName);
    }

    private function getProducerGroupKey(){
        return $this->getAppName() . ":" . $this->producerGroupKey;
    }

    private function getConsumerGroupKey(){
        return $this->getAppName() . ":" . $this->consumerGroupKey;
    }

    private function getAppName(){
        return strtolower(ConfigUtil::getAppName(LoggerConfig::getConfig()));
    }

}