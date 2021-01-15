<?php
namespace ybrenLib\rocketmq;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\cache\Cache;
use ybrenLib\rocketmq\consumer\DefaultMQConsumer;
use ybrenLib\rocketmq\core\ConcurrentMap;
use ybrenLib\rocketmq\entity\FindBrokerResult;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\entity\TopicPublishInfo;
use ybrenLib\rocketmq\entity\TopicRouteData;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\remoting\heartbeat\ConsumerData;
use ybrenLib\rocketmq\remoting\RemotingSyncClient;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\producer\DefaultMQProducer;
use ybrenLib\rocketmq\util\MixUtil;
use ybrenLib\rocketmq\util\MQClientUtil;

class MQClientInstance
{
    /**
     * @var Logger
     */
    private $log;

    protected $namesrvAddr;

    /**
     * @var RemotingSyncClient[]
     */
    protected $_syncClient;

    /**
     * 缓存
     * @var Cache
     */
    protected $cache;

    /**
     * @var DefaultMQConsumer[]
     */
    protected $consumerTable = [];

    /**
     * @var DefaultMQProducer[]
     */
    protected $producerTable = [];

    /**
     * @var DefaultMQProducer
     */
    protected $defaultMQProducer;
    /**
     * 是否关机
     * @var bool
     */
    protected $stopped = false;

    public function __construct($namesrvAddr = ""){
        // 创建同步阻塞客户端
        $this->namesrvAddr = $namesrvAddr;
        $this->cache = RocketMQConfig::getCache();
        $this->log = LoggerFactory::getLogger(MQClientInstance::class);
        $this->_syncClient = new ConcurrentMap();
        $this->defaultMQProducer = new DefaultMQProducer(MixUtil::$CLIENT_INNER_PRODUCER_GROUP);
    }

    /**
     * @param $topic
     * @return TopicPublishInfo
     * @throws exception\RocketMQClientException
     */
    public function tryToFindTopicPublishInfo($topic){
        $topicRouteData = $this->cache->getTopicRoute($topic);
        if(empty($topicRouteData)){
            $topicRouteData = $this->updateTopicPublishInfoFromNamesrv($topic);
        }
        return MQClientUtil::topicRouteData2TopicPublishInfo($topic , $topicRouteData);
    }

    /**
     * @param $topic
     * @return TopicRouteData
     * @throws exception\RocketMQClientException
     */
    public function updateTopicPublishInfoFromNamesrv($topic){
        $topicRouteData = MQClientApi::getRouteInfoFromNamrsrv($this->getNamesrvClient() , $topic);
        $this->cache->updateTopicRoute($topic , $topicRouteData);

        $subscribeInfo = MQClientUtil::topicRouteData2TopicSubscribeInfo($topic, $topicRouteData);

        // update consumer
        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $defaultMQConsumer){
                $defaultMQConsumer->updateTopicSubscribeInfo($topic , $subscribeInfo);
            }
        }

        return $topicRouteData;
    }

    /**
     * @param RemotingCommand $remotingCommand
     * @param string $brokerName
     * @return RemotingCommand
     * @throws RocketMQClientException
     */
    public function sendAndRecv(RemotingCommand $remotingCommand , string $brokerName){
        $brokerAddr = $this->findBrokerAddressInAdmin($brokerName);
        if(empty($brokerAddr)){
            throw new RocketMQClientException("there is not broker aliveable");
        }
        $client = $this->getOrCreateSyncClient($brokerAddr);
        return $client->send($remotingCommand);
    }

    /**
     * @return RemotingSyncClient|null
     */
    private function getNamesrvClient(){
        $namesrcAddrs = explode(";",$this->namesrvAddr);
        shuffle($namesrcAddrs);
        $client = null;
        foreach ($namesrcAddrs as $value){
            try{
                $client = $this->getOrCreateSyncClient($value);
                break;
            }catch (\Exception $e){
            }
        }
        return $client;
    }

    /**
     * @param $addr
     * @return RemotingSyncClient
     */
    public function getOrCreateSyncClient($addr){
        if($this->_syncClient->contains($addr)){
            return $this->_syncClient->get($addr);
        }
        return $this->createSyncClient($addr);
    }

    /**
     * @param $addr
     * @return RemotingSyncClient
     */
    public function createSyncClient($addr){
        $this->_syncClient->putIfAbsent($addr , null , function (&$value) use ($addr){
            $value = new RemotingSyncClient($addr);
        });
        return $this->_syncClient->get($addr);
    }

    /**
     * @param string $brokerName
     * @return null
     */
    public function findBrokerAddressInAdmin(string $brokerName) {
        $map = $this->cache->getBroker($brokerName);
        if (!empty($map)) {
            foreach ($map as $id => $brokerAddr) {
                if (!empty($brokerAddr)) {
                    if (MQConstants::MASTER_ID == $id) {
                        return $brokerAddr;
                    }
                }
            }
        }
        return null;
    }

    /**
     * @param string $topic
     * @param String $group
     * @return array|null
     * @throws RocketMQClientException
     */
    public function findConsumerIdList(string $topic, String $group) {
        $brokerAddr = $this->findBrokerAddrByTopic($topic);
        if (null == $brokerAddr) {
            $this->updateTopicPublishInfoFromNamesrv($topic);
            $brokerAddr = $this->findBrokerAddrByTopic($topic);
        }

        if (null != $brokerAddr) {
            try {
                return MQClientApi::getConsumerIdListByGroup($this->getOrCreateSyncClient($brokerAddr) , $group);
            } catch (\Exception $e) {
                $this->log->warn("getConsumerIdListByGroup exception, " . $brokerAddr . " " . $group. " error: ".$e->getMessage());
            }
        }

        return null;
    }

    public function findBrokerAddrByTopic(string $topic) {
        $topicRouteData = $this->cache->getTopicRoute($topic);
        if (!empty($topicRouteData)) {
            $brokers = $topicRouteData->getBrokerDatas();
            if (!empty($brokers)) {
                $brokerSize = count($brokers);
                $index = rand(0 , ($brokerSize - 1));
                $bd = $brokers[$index % $brokerSize];
                return $bd->selectBrokerAddr();
            }
        }
        return null;
    }

    /**
     * @param $brokerName
     * @param $brokerId
     * @param $onlyThisBroker
     * @return FindBrokerResult|null
     */
    public function findBrokerAddressInSubscribe(
        $brokerName,
        $brokerId,
        $onlyThisBroker
    ) {
        $brokerAddr = null;
        $slave = false;
        $found = false;

        $map = $this->cache->getBroker($brokerName);
        if (!empty($map)) {
            $brokerAddr = $map[$brokerId] ?? null;
            $slave = $brokerId != MQConstants::MASTER_ID;
            $found = $brokerAddr != null;

            if (!$found && !$onlyThisBroker) {
                $entry = key($map);
                $brokerAddr = $map[$entry];
                $slave = $entry != MQConstants::MASTER_ID;
                $found = true;
            }
        }

        if ($found) {
            return new FindBrokerResult($brokerAddr, $slave);
        }

        return null;
    }

    /**
     * @param MessageQueue $mq
     * @return int|mixed
     */
    public function maxOffset(MessageQueue $mq){
        $brokerAddr = $this->findBrokerAddressInAdmin($mq->getBrokerName());
        return MQClientApi::getMaxOffset($this->getOrCreateSyncClient($brokerAddr) , $mq->getTopic() , $mq->getQueueId());
    }

    /**
     * 注册生产者
     * @param $producerGroup
     * @param DefaultMQProducer $defaultMQProducer
     */
    public function registerProducer($producerGroup , DefaultMQProducer $defaultMQProducer){
        $this->cache->addProducer($producerGroup);
        $this->producerTable[$producerGroup] = $defaultMQProducer;
    }

    /**
     * @param mixed|string $namesrvAddr
     */
    public function setNamesrvAddr(string $namesrvAddr)
    {
        $this->namesrvAddr = $namesrvAddr;
    }

    /**
     * @return DefaultMQProducer
     */
    public function getDefaultMQProducer(): DefaultMQProducer
    {
        return $this->defaultMQProducer;
    }

    public function getClientId(){
        return (defined("LOCAL_HOST") ? LOCAL_HOST : "") . "@" . (defined("LOCAL_PORT") ? LOCAL_PORT : "");
    }

    /**
     * @return bool
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * @param bool $stopped
     */
    public function setStopped(bool $stopped)
    {
        $this->stopped = $stopped;
    }
}