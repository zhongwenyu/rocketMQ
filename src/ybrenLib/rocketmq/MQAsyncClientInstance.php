<?php
namespace ybrenLib\rocketmq;

use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\consumer\DefaultMQConsumer;
use ybrenLib\rocketmq\consumer\MQConsumerInner;
use ybrenLib\rocketmq\consumer\PullMessageService;
use ybrenLib\rocketmq\core\ConcurrentMap;
use ybrenLib\rocketmq\remoting\header\broker\UnregisterClientRequestHeader;
use ybrenLib\rocketmq\remoting\heartbeat\ConsumerData;
use ybrenLib\rocketmq\remoting\heartbeat\HeartbeatData;
use ybrenLib\rocketmq\remoting\heartbeat\ProducerData;
use ybrenLib\rocketmq\remoting\RemotingAsyncClient;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\util\ScheduleTaskUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class MQAsyncClientInstance extends MQClientInstance
{
    private $log;
    private $_clientId;
    /**
     * 是否启动
     * @var bool
     */
    private $started = false;
    /**
     * 负载均衡执行时间间隔，单位秒
     * @var int
     */
    private $_rebalanceInterval = 5;
    /**
     * 保持连接的broker，key是brokerName，value是addr
     * @var array
     */
    private $_brokers = [];
    /**
     * 心跳间隔，单位秒
     * @var int
     */
    private $_heartbeatInterval = 30;
    /**
     * 上传消费进度时间间隔，单位秒
     * @var int
     */
    private $_persistOffsetInterval = 10;
    /**
     * 从namesrv更细topic路由信息时间间隔
     * @var int
     */
    private $_updateTopicRouteInfoInterval = 30;
    /**
     * 多少次心跳不通后进行重连
     * @var int
     */
    private $_offlineNums = 3;
    /**
     * @var RemotingAsyncClient[]
     */
    private $_asyncClientTable;
    /**
     * @var PullMessageService
     */
    private $pullMessageService;

    public function __construct($namesrvAddr){
        // 定义clientId
        $this->_clientId = $this->getClientId();
        $this->log = LoggerFactory::getLogger(MQAsyncClientInstance::class);
        $this->_asyncClientTable = new ConcurrentMap();
        parent::__construct($namesrvAddr);
    }

    public function start(){
        if(!$this->started){
            $this->started = true;
            // 初始化消费者拉取消息服务
            $this->pullMessageService = new PullMessageService($this);
            // 初始化连接客户端
            $this->sendHeartbeatToAllClient();
            ScheduleTaskUtil::after(1*1000 , function (){
                // 注册生产者和消费者
                $this->sendHeartbeatToAllClient();
            });
            ScheduleTaskUtil::timer($this->_heartbeatInterval*1000, function ($timer_id){
                // 发送心跳请求到所有客户端
                $this->sendHeartbeatToAllClient();
            });
            ScheduleTaskUtil::timer($this->_rebalanceInterval*1000, function ($timer_id){
                // 负载均衡，第一次负载均衡是等20s后，即便有新的消费者增加进来，也会等老的消费者进行一轮负载均衡后再获取队列的了
                // var_dump("begin to doRebalance......");
                $this->doRebalance();
            });
            ScheduleTaskUtil::timer($this->_persistOffsetInterval*1000, function ($timer_id){
                // 定时上传消费进度到broker
                // var_dump("begin to persistOffset......");
                if(!empty($this->consumerTable)){
                    foreach ($this->consumerTable as $impl){
                        $impl->persistConsumerOffset();
                    }
                }
            });
            ScheduleTaskUtil::timer($this->_updateTopicRouteInfoInterval*1000, function ($timer_id){
                // 定时从namesrv更新topic路由信息
                $this->updateTopicRouteInfoFromNameServer();
            });
            ScheduleTaskUtil::timer(10*1000 , function ($timer_id){
                // 监听关机信号
                if($this->cache->isStopped()){
                    try{
                        // 优雅关机
                        $this->shutdown();
                    } finally {
                        $this->cache->rmStopped();
                    }
                }
            });
        }
    }

    /**
     * 从namesrc更新broker信息
     */
    public function updateTopicRouteInfoFromNameServer(){
        $topics = [];
        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $impl){
                !in_array($impl->getTopic() , $topics) && $topics[] = $impl->getTopic();
            }
        }

        $produceAllTopics = $this->cache->getAllTopic();
        if(!empty($produceAllTopics)){
            $topics = array_merge($topics , $produceAllTopics);
        }

        if(!empty($topics)){
            foreach ($topics as $topic){
                $this->updateTopicPublishInfoFromNamesrv($topic);
            }
        }
    }

    /**
     * 关机
     */
    public function shutdown(){
        if($this->isStopped()){
            return;
        }
        $this->log->info("start shutdown......");
        $this->setStopped(true);
        // 停止从broker pullMessage
        $this->pullMessageService->shutdown();

        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $impl){
                $impl->shutdown();
            }
            $this->consumerTable = null;
        }

        // 向broker通知消费者生产者下线
        if(!empty($this->_asyncClientTable)){
            foreach ($this->_asyncClientTable as $client){

                // consumer
                $this->unregisterConsumerClient($client);

                // producer
                $this->unregisterProducerClient($client);

                // 关闭客户端
                $client->close();
            }
            $this->_asyncClientTable = null;
        }

        // 关闭所有定时任务
        ScheduleTaskUtil::stopAll();

        // 清楚缓存
        $this->cache->clearAll();

        $this->log->info("shutdown success......");
    }

    /**
     * 注册消费者
     * @param $consumerGroup
     * @param DefaultMQConsumer $defaultMQConsumer
     */
    public function registerConsumer($consumerGroup , DefaultMQConsumer $defaultMQConsumer){
        //   $this->cache->addConsumer($consumerGroup , new ConsumerData($defaultMQConsumer));
        $this->consumerTable[$consumerGroup] = $defaultMQConsumer;
    }

    /**
     * 负载均衡
     */
    public function doRebalance() {
        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $consumerGroup => $impl) {
                if ($impl != null) {
                    try {
                        $impl->doRebalance();
                    } catch (\Exception $e) {
                        $this->log->error($consumerGroup . " doRebalance error: ".$e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * @param RemotingAsyncClient $client
     */
    private function unregisterProducerClient(RemotingAsyncClient $client){
        $allproducers = $this->cache->getProducer();
        $unregisterClientRequestHeader = new UnregisterClientRequestHeader();
        $unregisterClientRequestHeader->setClientID($this->_clientId);
        if(!empty($allproducers)){
            foreach ($allproducers as $producer){
                $unregisterClientRequestHeader->setProducerGroup($producer);
                $remotingCommand = RemotingCommand::createRequestCommand(RequestCode::$UNREGISTER_CLIENT , $unregisterClientRequestHeader);
                try{
                    $client->send($remotingCommand);
                    $this->log->info("unregister client ".$client->getAddr()." producer ".$producer." success......");
                }catch (\Exception $e){
                    $this->log->error("unregister client error:".$e->getMessage());
                }
            }
        }
    }

    /**
     * @param RemotingAsyncClient $client
     */
    private function unregisterConsumerClient(RemotingAsyncClient $client){
        $unregisterClientRequestHeader = new UnregisterClientRequestHeader();
        $unregisterClientRequestHeader->setClientID($this->_clientId);
        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $impl){
                $groupName = $impl->getConsumerGroup();
                $unregisterClientRequestHeader->setConsumerGroup($groupName);
                $remotingCommand = RemotingCommand::createRequestCommand(RequestCode::$UNREGISTER_CLIENT , $unregisterClientRequestHeader);
                try{
                    $client->send($remotingCommand);
                    $this->log->info("unregister client ".$client->getAddr()." consumer ".$groupName." success......");
                }catch (\Exception $e){
                    $this->log->error("unregister client error:".$e->getMessage());
                }
            }
        }
    }

    private function getAllConsumers(){
        $allconsumers = [];
        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $impl){
                $allconsumers[] = $impl->getConsumerGroup();
            }
        }
        return $allconsumers;
    }

    /**
     * 获取所有正在使用的broker
     */
    private function getBrokersFromTopicRoute(){
        $alltopic = $this->cache->getAllTopic();
        $brokers = [];
        if(!empty($alltopic)){
            foreach ($alltopic as $topic){
                $topicRouteData = $this->cache->getTopicRoute($topic);
                if(!empty($topicRouteData)){
                    $brokerData = $topicRouteData->getBrokerDatas();
                    if(!empty($brokerData)){
                        foreach ($brokerData as $brokerInfo){
                            $brokerAddrs = $brokerInfo->getBrokerAddrs();
                            if(!empty($brokerAddrs)){
                                foreach ($brokerAddrs as $id => $addr){
                                    if (!empty($addr)) {
                                        if (MQConstants::MASTER_ID == $id) {
                                            $brokers[$brokerInfo->getBrokerName()] = $addr;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        return $brokers;
    }

    /**
     * 发送心跳包到所有客户端
     */
    private function sendHeartbeatToAllClient(){
        $allbrokers = $this->getBrokersFromTopicRoute();
        $allProducers = $this->cache->getProducer();
        $allConsumers = $this->getAllConsumers();
        $this->log->info("启动心跳,producer:" . json_encode($allProducers)." consumer:".json_encode($allConsumers));
        if(empty($allbrokers) && !empty($this->_brokers)){
            $allbrokers = $this->_brokers;
        }
        $time = TimeUtil::currentTimeMillis();
        if(!empty($allbrokers) && (!empty($allProducers) || !empty($allConsumers))){
            // 清除不使用的broker，比如发生了主从切换
            $this->cleanOfflineBroker(array_values($allbrokers));

            $heartbeatData = $this->prepareHeartbeatData($allProducers , $allConsumers);
            $request = RemotingCommand::createRequestCommand(RequestCode::$HEART_BEAT);
            // var_dump("send heart:" . json_encode($request));
            $request->setBody(json_encode($heartbeatData));
            foreach ($allbrokers as $brokerName => $brokerAddr){
                if($this->_asyncClientTable->contains($brokerAddr)){
                    $client = $this->_asyncClientTable[$brokerAddr];
                    if(!$client->isConnected() || ($time - $client->getLastHeartbeatTime()) > $this->_offlineNums*$this->_heartbeatInterval*1000){
                        // 移除客户端
                        $this->removeAsyncClient($brokerAddr);
                        // 初始化客户端
                        $this->createAsyncClient($brokerAddr);
                    }else{
                        // 发送心跳包
                        $client->send($request);
                    }
                }else{
                    // 初始化客户端
                    $this->createAsyncClient($brokerAddr);
                }

            }
        }
    }

    /**
     * 清除不使用的broker
     * @param $brokerAddrs
     */
    private function cleanOfflineBroker($brokerAddrs){
        $keys = $this->_asyncClientTable->keys();
        foreach ($keys as $addr){
            if(!in_array($addr , $brokerAddrs)){
                $this->removeAsyncClient($addr);
            }
        }
    }

    /**
     * @param $allProducer
     * @param MQConsumerInner[] $allConsumer
     * @return HeartbeatData
     */
    private function prepareHeartbeatData($allProducer , $allConsumer) {
        $heartbeatData = new HeartbeatData();

        // clientID
        $heartbeatData->setClientID($this->_clientId);

        // Producer
        $producerDataSet = [];
        if(!empty($allProducer)){
            foreach ($allProducer as $groupName){
                $producerData = new ProducerData();
                $producerData->setGroupName($groupName);
                $producerDataSet[] = $producerData;
            }
        }

        // Consumer
        $consumerDataSet = [];
        if(!empty($this->consumerTable)){
            foreach ($this->consumerTable as $impl) {
                $consumerDataSet[] = new ConsumerData($impl);
            }
        }

        !empty($producerDataSet) && $heartbeatData->setProducerDataSet($producerDataSet);
        !empty($consumerDataSet) && $heartbeatData->setConsumerDataSet($consumerDataSet);
        return $heartbeatData;
    }

    /**
     * 获取或创建异步客户端
     * @param $addr
     * @return RemotingAsyncClient
     */
    public function getOrCreateAsyncClient($addr){
        if($this->_asyncClientTable->contains($addr)){
            $client = $this->_asyncClientTable->get($addr);
            if($client->isConnected()){
                return $client;
            }else{
                $this->_asyncClientTable->remove($addr);
            }
        }
        return $this->createAsyncClient($addr);
    }

    /**
     * 创建异步客户端
     * @param $addr
     * @return RemotingAsyncClient
     */
    public function createAsyncClient($addr){
        $this->_asyncClientTable->putIfAbsent($addr , null , function (&$value) use ($addr){
            $value = new RemotingAsyncClient($addr);
        });
        return $this->_asyncClientTable->get($addr);
    }

    /**
     * 移除客户端
     * @param $brokerAddr
     */
    private function removeAsyncClient($brokerAddr){
        try{
            $client = $this->_asyncClientTable->remove($brokerAddr);
            !is_null($client) && $client->close();
        }catch (\Exception $e){
            $this->log->warn("close client error:" . $e->getMessage());
        }
    }

    /**
     * @param string $group
     * @return DefaultMQConsumer|null
     */
    public function selectConsumer(string $group) {
        return $this->consumerTable[$group] ?? null;
    }

    /**
     * @return PullMessageService
     */
    public function getPullMessageService(): PullMessageService
    {
        return $this->pullMessageService;
    }

    /**
     * @param PullMessageService $pullMessageService
     */
    public function setPullMessageService(PullMessageService $pullMessageService)
    {
        $this->pullMessageService = $pullMessageService;
    }
}