<?php
namespace ybrenLib\rocketmq\remoting\processor;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\cache\Cache;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageSysFlag;
use ybrenLib\rocketmq\remoting\AbstractRemotingClient;
use ybrenLib\rocketmq\remoting\ByteBuf;
use ybrenLib\rocketmq\remoting\callback\InvokeCallback;
use ybrenLib\rocketmq\remoting\header\broker\EndTransactionRequestHeader;
use ybrenLib\rocketmq\remoting\MessageDecoder;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\producer\LocalTransactionState;
use ybrenLib\rocketmq\RocketMQConfig;

class EndTransactionProcessor implements Processor
{
    /**
     * @var Cache
     */
    private $cache;
    /**
     * @var Logger
     */
    private $log;
    
    public function __construct()
    {
        $this->cache = RocketMQConfig::getCache();
        $this->log = LoggerFactory::getLogger(EndTransactionProcessor::class);
    }

    /**
     * 事务回查
     * @param AbstractRemotingClient $client
     * @param RemotingCommand $remotingCommand
     * @param InvokeCallback|null $invokeCallback
     */
    public function execute(AbstractRemotingClient $client , RemotingCommand $remotingCommand , InvokeCallback $invokeCallback = null){
        $body = $remotingCommand->getBody();
        $byteBuf = new ByteBuf($body);
        $messageExt = MessageDecoder::decodeMessage($byteBuf , $remotingCommand);
        $producerGroup = $messageExt->getProperty(MessageConst::PROPERTY_PRODUCER_GROUP);
        $topic = $messageExt->getTopic();
        $transactionListener = $this->cache->getTransactionListener($producerGroup , $topic);
        if(!empty($transactionListener)){
            $remark = null;
            try{
                $localTransactionState = $transactionListener->checkLocalTransaction($messageExt);
            }catch (\Exception $e){
                $localTransactionState = LocalTransactionState::UNKNOW;
                $remark = $e->getMessage();
                $this->log->error("check transaction statr error");
            }
            // 发送响应
            $endTransactionRequestHeader = new EndTransactionRequestHeader();
            $endTransactionRequestHeader->setMsgId($messageExt->getMsgId());
            $endTransactionRequestHeader->setCommitLogOffset($messageExt->getCommitLogOffset());
            $endTransactionRequestHeader->setTranStateTableOffset($messageExt->getTranStateTableOffset());
            $endTransactionRequestHeader->setProducerGroup($producerGroup);
            $endTransactionRequestHeader->setFromTransactionCheck(true);
            $uniqueKey = $messageExt->getProperty(MessageConst::PROPERTY_UNIQ_CLIENT_MESSAGE_ID_KEYIDX);
            if ($uniqueKey == null) {
                $uniqueKey = $messageExt->getMsgId();
            }
            $endTransactionRequestHeader->setMsgId($uniqueKey);
            $endTransactionRequestHeader->setTransactionId($messageExt->getTransactionId());
            switch ($localTransactionState) {
                case LocalTransactionState::COMMIT_MESSAGE:
                    $endTransactionRequestHeader->setCommitOrRollback(MessageSysFlag::TRANSACTION_COMMIT_TYPE);
                    break;
                case LocalTransactionState::ROLLBACK_MESSAGE:
                    $endTransactionRequestHeader->setCommitOrRollback(MessageSysFlag::TRANSACTION_ROLLBACK_TYPE);
                    break;
                case LocalTransactionState::UNKNOW:
                    $endTransactionRequestHeader->setCommitOrRollback(MessageSysFlag::TRANSACTION_NOT_TYPE);
                    break;
                default:
                    break;
            }
            $this->log->info("check transaction state {} : {} - {}" , $localTransactionState , $messageExt->getMsgId() , $messageExt->getKeys());
            $request = RemotingCommand::createRequestCommand(RequestCode::$END_TRANSACTION, $endTransactionRequestHeader);
            $request->setRemark($remark);
            try{
                $client->send($request);
            }catch (\Exception $e){
                $this->log->error("reply transaction response message error:" . $e->getMessage());
            }
        }
    }

    function exception(\Exception $e)
    {
        // TODO: Implement exception() method.
    }


}