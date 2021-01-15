<?php
namespace ybrenLib\rocketmq\remoting;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\core\ResponseFuture;
use ybrenLib\rocketmq\exception\RocketMQClientException;

/**
 * 同步阻塞客户端
 * Class RemotingSyncClient
 * @package ybrenLib\rocketmq\netty
 */
class RemotingSyncClient extends AbstractRemotingClient
{
    /**
     * @var Logger
     */
    private $log;
    protected $addr;
    protected $client = null;

    public function __construct($addr){
        $this->log = LoggerFactory::getLogger(RemotingAsyncClient::class);
        $this->addr = $addr;
        $this->client = SwooleClientFactory::createSyncClient();
    }

    function connect()
    {
        $addrs = explode(":",$this->addr);
        $this->client->connect($addrs[0] , $addrs[1]);
    }

    function isConnected()
    {
        return $this->client->isConnected();
    }

    /**
     * @param RemotingCommand $remotingCommand
     * @param ResponseFuture|null $responseFuture
     * @return RemotingCommand
     * @throws RocketMQClientException
     */
    function send(RemotingCommand $remotingCommand , ResponseFuture $responseFuture = null)
    {
        $byteBuf = MessageEncoder::encode($remotingCommand);
        // 连接客户端
        $this->connect();
        try{
            $sendResult = $this->client->send($byteBuf->flush());
            if($sendResult === false){
                throw new RocketMQClientException("send data ".json_encode($remotingCommand)." to ".$this->addr." error: ".$this->client->errCode);
            }
            $recvData = $this->client->recv();
            return MessageDecoder::decode($recvData);
        } finally {
            // 关闭客户端
            $this->close();
        }
    }

    function close()
    {
        try{
            $this->client->close();
        }catch (\Exception $e){
            $this->log->warn("close client ".$this->addr." error:" . $e->getMessage());
        }
    }

    /**
     * @return mixed
     */
    public function getAddr()
    {
        return $this->addr;
    }
}