<?php
namespace ybrenLib\rocketmq\remoting;


class NettyClient{

    /**
     * @var SwooleTcpClient
     */
    private $client;

    private $connectFlag = false;

    public function __construct(){
        // 创建同步阻塞客户端
        $this->client = SwooleClientFactory::createSyncClient();
    }

    /**
     * @param $addr
     * @param $timeout
     * @throws \Exception
     */
    public function connect($addr , $timeout){
        $connectResult = $this->client->connect($addr , $timeout);
        if(!$connectResult){
            throw new \Exception("connect ".$addr." fail:" . $this->client->errCode);
        }
        $this->connectFlag = true;
    }

    public function shutdown(){
        if($this->connectFlag){
            $this->client->close();
            $this->connectFlag = false;
        }
    }

    /**
     * @param ByteBuf $byteBuf
     * @return mixed
     * @throws \Exception
     */
    public function send(ByteBuf $byteBuf){
        $msg = $byteBuf->flush();
        $sendResult = $this->client->send($msg);
        if($sendResult == false){
            throw new \Exception("send message error:" . $this->client->errCode);
        }
        $recvData =  $this->client->recv();
        if(empty($recvData)){
            throw new \Exception("get recv empty");
        }
        return $recvData;
    }
}