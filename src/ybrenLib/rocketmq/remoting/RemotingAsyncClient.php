<?php
namespace ybrenLib\rocketmq\remoting;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\core\ConcurrentMap;
use ybrenLib\rocketmq\core\ResponseFuture;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\remoting\processor\Processor;
use ybrenLib\rocketmq\util\TimeUtil;

/**
 * 异步非阻塞客户端
 * Class RemotingAsyncRemotingClient
 * @package ybrenLib\rocketmq\netty
 */
class RemotingAsyncClient extends AbstractRemotingClient implements RemotingClientAsyncListener
{
    /**
     * @var Logger
     */
    private $log;

    /**
     * @var ResponseFuture[]
     */
    private $responseTable;

    private $processors = [];

    private $linkStatus = null;
    private $lastHeartbeatTime = 0;

    public function __construct($addr){
        $this->responseTable = new ConcurrentMap();
        $this->log = LoggerFactory::getLogger(RemotingAsyncClient::class);
        $this->linkStatus = ClientLinkStatus::WAIT;
        $this->addr = $addr;
        $this->connect();
    }

    public function connect(){
        $this->lastHeartbeatTime = TimeUtil::currentTimeMillis();
        $client = SwooleClientFactory::createAsyncClient($this->addr , $this);
        $addrs = explode(":",$this->addr);
        $client->connect($addrs[0] , $addrs[1]);
    }

    function onConnect($client)
    {
        // var_dump("client ".$this->addr." connect.....");
        $this->client = $client;
        $this->linkStatus = ClientLinkStatus::CONNECTED;
        $this->lastHeartbeatTime = TimeUtil::currentTimeMillis();
    }

    function onReceive($client, $data)
    {
        $this->lastHeartbeatTime = TimeUtil::currentTimeMillis();
        $remotingCommand = MessageDecoder::decode($data);
        // var_dump("recieve: ".json_encode($remotingCommand));
        $opaque = $remotingCommand->getOpaque();
        if($this->responseTable->contains($opaque)){
            $responseFuture = $this->responseTable->remove($opaque);
            try{
                $responseFuture->setResponse($remotingCommand);
                $responseFuture->executeInvokeCallback();
            }catch (\Exception $e){
                $this->log->error("callback remotingcomman: ".json_encode($remotingCommand)." error:" . $e->getMessage());
                $responseFuture->setCause($e);
                // var_dump($e->getMessage());
            } finally {
                $responseFuture->setResponseOk();
            }
        }else if(isset($this->processors[$remotingCommand->getCode()])){
            foreach ($this->processors[$remotingCommand->getCode()] as $processor){
                try {
                    $processor->execute($this , $remotingCommand);
                }catch (\Exception $e){
                    $this->log->error("receive ".json_encode($remotingCommand)." run processor ".get_class($processor)." error: ".$e->getMessage());
                }
            }
        }
    }

    function onClose($client)
    {
        $this->linkStatus = ClientLinkStatus::OFFLINE;
        $this->log->error("link ".$this->addr." close");
        // var_dump("link ".$this->addr." close");
    }

    function onError($client)
    {
        $this->linkStatus = ClientLinkStatus::OFFLINE;
        $this->log->error("link ".$this->addr." error: ".$client->errCode);
        // var_dump("link ".$this->addr." error: ".$client->errCode);
    }

    /**
     * @param RemotingCommand $remotingCommand
     * @param ResponseFuture|null $responseFuture
     * @throws RocketMQClientException
     */
    public function send(RemotingCommand $remotingCommand , ResponseFuture $responseFuture = null){
        $opaque = $remotingCommand->getOpaque();
        if($responseFuture != null){
            $this->responseTable->put($opaque , $responseFuture);
        }
        $byteBuf = MessageEncoder::encode($remotingCommand);
        $sendResult = $this->client->send($byteBuf->flush());
        if($sendResult === false){
            $e = new RocketMQClientException("send data ".json_encode($remotingCommand)." to ".$this->addr." error: ".$this->client->errCode);
            if($responseFuture != null){
                $responseFuture->setCause($e);
                $responseFuture->setResponseOk();
            }
            throw $e;
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isConnected(){
        return $this->client != null && $this->linkStatus == ClientLinkStatus::CONNECTED && $this->client->isConnected();
    }

    public function close(){
        try{
            $this->linkStatus = ClientLinkStatus::OFFLINE;
            $this->client->close();
        }catch (\Exception $e){
            $this->log->warn("close client ".$this->addr." error:" . $e->getMessage());
        }
    }

    /**
     * 注册请求处理器
     * @param int $code
     * @param Processor $processor
     */
    public function registerProcessor(int $code , Processor $processor){
        !isset($this->processors[$code]) && $this->processors[$code] = [];
        $this->processors[$code][] = $processor;
    }

    /**
     * @return int
     */
    public function getLinkStatus(): int
    {
        return $this->linkStatus;
    }

    /**
     * @return int
     */
    public function getLastHeartbeatTime(): int
    {
        return $this->lastHeartbeatTime;
    }

    function getAddr()
    {
        return $this->addr;
    }
}