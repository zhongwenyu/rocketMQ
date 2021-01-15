<?php
namespace ybrenLib\rocketmq;

use ybrenLib\rocketmq\entity\TopicRouteData;
use ybrenLib\rocketmq\remoting\header\namesrv\GetRouteInfoRequestHeader;
use ybrenLib\rocketmq\remoting\NettyClient;
use ybrenLib\rocketmq\remoting\MessageDecoder;
use ybrenLib\rocketmq\remoting\MessageEncoder;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\RequestCode;
use ybrenLib\rocketmq\util\MQClientUtil;

class MQClient
{
    /**
     * @var NettyClient
     */
    private $nettyClient;

    public function __construct(){
        $this->nettyClient = new NettyClient();
    }


    /**
     * @param RemotingCommand $requestCommand
     * @return RemotingCommand
     * @throws exception\RocketMQClientException
     */
    public function sendMessage(RemotingCommand $requestCommand){
        $byteBuf = MessageEncoder::encode($requestCommand);
        $result = $this->nettyClient->send($byteBuf);
        $remotingCommand = MessageDecoder::decode($result);
        return $remotingCommand;
    }

    public function connect($addr){
        $this->nettyClient->connect($addr , 10);
    }

    public function shutdown(){
        $this->nettyClient->shutdown();
    }
}