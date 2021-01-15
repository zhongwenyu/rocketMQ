<?php
namespace ybrenLib\rocketmq\producer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\entity\Message;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageSysFlag;
use ybrenLib\rocketmq\entity\SendResult;
use ybrenLib\rocketmq\entity\TransactionSendResult;
use ybrenLib\rocketmq\remoting\header\broker\EndTransactionRequestHeader;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\remoting\MessageDecoder;

class TransactionMQProducer extends DefaultMQProducer
{
    /**
     * @var TransactionListener
     */
    private $transactionListener = null;

    /**
     * @var Logger
     */
    private $log;

    /**
     * 事务回查时间，默认10分钟
     * @var float|int
     */
    private $checkImmunityTime = 5;

    public function __construct($producerGroup)
    {
        $this->log = LoggerFactory::getLogger(TransactionMQProducer::class);
        parent::__construct($producerGroup);
    }

    /**
     * 发送事务消息
     * @param Message $msg
     * @param $arg
     * @return TransactionSendResult
     * @throws \ybrenLib\rocketmq\exception\RocketMQClientException
     */
    public function sendMessageInTransaction(Message $msg , $arg = null){
        if($arg == null){
            $arg = $msg;
        }
        if(!is_null($this->transactionListener)){
            // 注册事务回查
            $this->cache->addTransactionListener($this->getProducerGroup() , $msg->getTopic() , $this->transactionListener);
        }
        // 忽略延时属性
        $msg->clearProperty(MessageConst::PROPERTY_DELAY_TIME_LEVEL);
        // 标识是事务消息
        $msg->putProperty(MessageConst::PROPERTY_TRANSACTION_PREPARED , "true");
        // 指定生产组
        $msg->putProperty(MessageConst::PROPERTY_PRODUCER_GROUP , $this->getProducerGroup());
        // 设置事务回查时间
        $msg->putProperty(MessageConst::PROPERTY_CHECK_IMMUNITY_TIME_IN_SECONDS , $this->checkImmunityTime);
        // 发送消息
        $sendResult = $this->send($msg);
        $localTransactionState = LocalTransactionState::UNKNOW;
        switch ($sendResult->getSendStatus()){
            case "SEND_OK":
                $transactionId = $msg->getProperty(MessageConst::PROPERTY_UNIQ_CLIENT_MESSAGE_ID_KEYIDX);
                if (!empty($transactionId)) {
                    $msg->setTransactionId($transactionId);
                }
                if ($this->transactionListener != null) {
                    // 执行本地事务
                    $localTransactionState = $this->transactionListener->executeLocalTransaction($msg , $sendResult, $arg);
                }
                if (empty($localTransactionState)) {
                    $localTransactionState = LocalTransactionState::UNKNOW;
                }
                break;
            case "FLUSH_DISK_TIMEOUT":
            case "FLUSH_SLAVE_TIMEOUT":
            case "SLAVE_NOT_AVAILABLE":
                $localTransactionState = LocalTransactionState::ROLLBACK_MESSAGE;
                break;
            default:
                break;
        }

        if($localTransactionState != LocalTransactionState::UNKNOW){
            try{
                $this->endTransaction($sendResult , $localTransactionState);
            }catch (\Exception $e){
                $this->log->error("{} message {} error: {}" , $localTransactionState , $sendResult->getMsgId() , $e->getMessage());
            }
        }

        $transactionSendResult = new TransactionSendResult();
        $transactionSendResult->setSendStatus($sendResult->getSendStatus());
        $transactionSendResult->setMessageQueue($sendResult->getMessageQueue());
        $transactionSendResult->setMsgId($sendResult->getMsgId());
        $transactionSendResult->setQueueOffset($sendResult->getQueueOffset());
        $transactionSendResult->setTransactionId($sendResult->getTransactionId());
        $transactionSendResult->setLocalTransactionState($localTransactionState);
        $transactionSendResult->setBrokerName($sendResult->getBrokerName());
        $transactionSendResult->setMsgKeys($sendResult->getMsgKeys());
        return $transactionSendResult;
    }

    /**
     * 提交事务消息
     * @param TransactionSendResult $sendResult
     */
    public function commit(TransactionSendResult $sendResult){
        return $this->endTransaction($sendResult , LocalTransactionState::COMMIT_MESSAGE);
    }

    /**
     * 回滚事务消息
     * @param TransactionSendResult $sendResult
     */
    public function rollback(TransactionSendResult $sendResult){
        return $this->endTransaction($sendResult , LocalTransactionState::ROLLBACK_MESSAGE);
    }

    /**
     * 事务处理
     * @param SendResult $sendResult
     * @param $localTransactionState
     * @return RemotingCommand
     * @throws \ybrenLib\rocketmq\exception\RocketMQClientException
     */
    private function endTransaction(SendResult $sendResult , $localTransactionState){
        if (!empty($sendResult->getOffsetMsgId())) {
            $msgId = $sendResult->getOffsetMsgId();
        } else {
            $msgId = $sendResult->getMsgId();
        }
        $commitLogOffset = MessageDecoder::decodeMessageId($msgId);

        // 发送响应
        $endTransactionRequestHeader = new EndTransactionRequestHeader();
        $endTransactionRequestHeader->setMsgId($msgId);
        $endTransactionRequestHeader->setCommitLogOffset($commitLogOffset);
        $endTransactionRequestHeader->setTranStateTableOffset($sendResult->getQueueOffset());
        $endTransactionRequestHeader->setProducerGroup($this->getProducerGroup());
        $endTransactionRequestHeader->setFromTransactionCheck(false);
        $endTransactionRequestHeader->setTransactionId($sendResult->getTransactionId());
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
        $this->log->info("{} message : {}" , $localTransactionState , $msgId);
        $request = RemotingCommand::createRequestCommand(RequestCode::$END_TRANSACTION, $endTransactionRequestHeader);
        return $this->mqClientInstance->sendAndRecv($request , $sendResult->getBrokerName());
    }

    /**
     * @param TransactionListener $transactionListener
     */
    public function setTransactionListener(TransactionListener $transactionListener)
    {
        $this->transactionListener = $transactionListener;
    }

    /**
     * @param float|int $checkImmunityTime
     */
    public function setCheckImmunityTime($checkImmunityTime)
    {
        $this->checkImmunityTime = $checkImmunityTime;
    }
}