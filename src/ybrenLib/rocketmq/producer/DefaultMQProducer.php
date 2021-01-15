<?php
namespace ybrenLib\rocketmq\producer;

use Closure;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\cache\Cache;
use ybrenLib\rocketmq\entity\Message;
use ybrenLib\rocketmq\entity\MessageBatch;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\entity\MessageSysFlag;
use ybrenLib\rocketmq\entity\SendResult;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\MQClientInstanceFactory;
use ybrenLib\rocketmq\remoting\header\broker\SendMessageRequestHeader;
use ybrenLib\rocketmq\MQClientInstance;
use ybrenLib\rocketmq\MQConstants;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\RocketMQConfig;
use ybrenLib\rocketmq\strategy\MQFaultStrategy;
use ybrenLib\rocketmq\remoting\MessageDecoder;
use ybrenLib\rocketmq\util\MQClientUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class DefaultMQProducer{

    /**
     * @var MQClientInstance
     */
    protected $mqClientInstance;
    
    private $mqFaultStrategy;

    private $producerGroup;

    private $unitMode = false;

    private $namesrvAddr;

    private $flag = false;

    // 上一次选择的broker
    protected $lastBrokerName = null;

    /**
     * @var MQProducerFallback
     */
    protected $mqProducerFallBack = null;

    /**
     * 缓存
     * @var Cache
     */
    protected $cache;

    public function __construct($producerGroup){
        $this->mqFaultStrategy = new MQFaultStrategy();
        $this->producerGroup = $producerGroup;
        $this->cache = RocketMQConfig::getCache();
    }

    /**
     * 启动
     */
    public function start(){
        if(!$this->flag){
            // 创建mqinstance
            $this->mqClientInstance = MQClientInstanceFactory::createSync($this->namesrvAddr);
            // 注册producer
            $this->registerProducer();
        }
    }

    /**
     * @param Message $message
     * @param Closure|null $selectQueue
     * @return SendResult
     * @throws RocketMQClientException
     */
    public function send(Message $message , Closure $selectQueue = null){
        $msgKey = $message->getKeys();
        if(empty($msgKey)){
            // 生成默认key
            $msgKey = MQClientUtil::createMessageKey($message->getTopic());
            $message->setKeys($msgKey);
        }
        $topicPublishInfo = $this->mqClientInstance->tryToFindTopicPublishInfo($message->getTopic());

        if($topicPublishInfo != null){
            // 定义返回
            $sendResult = null;
            while (true){
                try{
                    if(is_null($selectQueue)){
                        // 选择队列
                        $queue = $this->mqFaultStrategy->selectOneMessageQueue($topicPublishInfo , $this->lastBrokerName);
                    }else{
                        $queue = $selectQueue($topicPublishInfo->getMessageQueueList(), $message);
                    }
                    if($queue == null){
                        throw new RocketMQClientException("queue is null");
                    }
                    // 本次连接brokerName
                    $this->lastBrokerName = $queue->getBrokerName();
                    // 发送消息
                    $remotingCommand = $this->sendKernelImpl($message , $queue);
                    // 构建返回
                    $sendResult = $this->formatSendResultFromRemotingCommand($remotingCommand , $queue , $msgKey);

                    if(!is_null($this->mqProducerFallBack)){
                        $this->mqProducerFallBack->afterSendMessage($message , $sendResult);
                    }

                    return $sendResult;
                }catch (\Throwable $e){
                    LoggerFactory::getLogger(DefaultMQProducer::class)->warn("send message to queue:".json_encode($queue)." fail:".$e->getMessage());
                }
            }
        }
    }

    /**
     * @param RemotingCommand $remotingCommand
     * @param MessageQueue $queue
     * @return SendResult
     */
    protected function formatSendResultFromRemotingCommand(RemotingCommand  $remotingCommand , MessageQueue $queue , $msgKey){
        $sendResult = new SendResult();
        $extFields = $remotingCommand->getExtFields();
        $sendResult->setMessageQueue($extFields['queueId']);
        $sendResult->setMsgId($extFields['msgId']);
        $sendResult->setRegionId($extFields['MSG_REGION']);
        $sendResult->setQueueOffset($extFields['queueOffset']);
        $sendResult->setTraceOn($extFields['TRACE_ON'] == "true");
        $sendResult->setBrokerName($queue->getBrokerName());
        $sendResult->setMsgKeys($msgKey);
        return $sendResult;
    }

    /**
     * 注册生产者
     */
    protected function registerProducer(){
        $this->mqClientInstance->registerProducer($this->producerGroup , $this);
    }

    /**
     * 发送消息
     * @param Message $msg
     * @param MessageQueue $mq
     * @return RemotingCommand
     * @throws RocketMQClientException
     */
    protected function sendKernelImpl(Message $msg , MessageQueue $mq){
        $sysFlag = MessageSysFlag::DEFAULT;

        $tranMsg = $msg->getProperty(MessageConst::PROPERTY_TRANSACTION_PREPARED);
        if ($tranMsg == "true") {
            $sysFlag = MessageSysFlag::PREPARE_TRANS_MSG;
        }

        if(!is_null($this->mqProducerFallBack)){
            $this->mqProducerFallBack->beforeSendMessage($msg);
        }

        // 构建请求
        $requestHeader = new SendMessageRequestHeader();
        $requestHeader->setProducerGroup($this->producerGroup);  // 生产者
        $requestHeader->setTopic($msg->getTopic());  // 主题
        // 默认创建主题key
        $requestHeader->setDefaultTopic(MQConstants::AUTO_CREATE_TOPIC_KEY_TOPIC);
        // 主题在单个broker默认队列数
        $requestHeader->setDefaultTopicQueueNums(MQConstants::DEFAULT_TOPIC_QUEUE_NUMS);
        $requestHeader->setQueueId($mq->getQueueId());  // 队列序号
        $requestHeader->setSysFlag($sysFlag);  // 消息系统标记
        $requestHeader->setBornTimestamp(TimeUtil::currentTimeMillis());
        $requestHeader->setFlag($msg->getFlag());
        $requestHeader->setProperties(MessageDecoder::messageProperties2String($msg->getProperties())); // 扩展属性
        $requestHeader->setReconsumeTimes(0);  // 消息重试次数
        $requestHeader->setUnitMode($this->isUnitMode());
        $requestHeader->setBatch($msg instanceof MessageBatch);  // 是否是批量消息

        $request = RemotingCommand::createRequestCommand(RequestCode::$SEND_MESSAGE, $requestHeader);
        $request->setBody($msg->getBody());

        // 发送消息
        return $this->mqClientInstance->sendAndRecv($request , $mq->getBrokerName());
    }

    /**
     * 注册消息生产回调函数
     * @param MQProducerFallback $MQProducerFallback
     */
    public function registerFallback(MQProducerFallback $MQProducerFallback){
        $this->mqProducerFallBack = $MQProducerFallback;
    }

    /**
     * 关闭客户端连接
     */
    public function shutdown(){
        $this->mqClientInstance->shutdown();
    }

    /**
     * @return mixed
     */
    public function getProducerGroup()
    {
        return $this->producerGroup;
    }

    /**
     * @param mixed $producerGroup
     */
    public function setProducerGroup($producerGroup)
    {
        $this->producerGroup = $producerGroup;
    }

    /**
     * @param mixed $namesrvAddr
     */
    public function setNamesrvAddr($namesrvAddr)
    {
        $this->namesrvAddr = $namesrvAddr;
    }

    /**
     * @return bool
     */
    public function isUnitMode()
    {
        return $this->unitMode;
    }

    /**
     * @param bool $unitMode
     */
    public function setUnitMode($unitMode)
    {
        $this->unitMode = $unitMode;
    }

}