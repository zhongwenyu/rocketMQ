<?php
namespace ybrenLib\rocketmq\remoting;

class SwooleClientFactory
{
    public static function createSyncClient(){
        $client = new \Swoole\Client(SWOOLE_SOCK_TCP);
        $client->set(array(
            'open_length_check'     => true,
            'package_length_type'   => 'N',
            'package_max_length'    => 1024*1024*2, //协议最大长度
            'package_length_func'   => function ($data) {
                if (strlen($data) < 8) {
                    return 0;
                }
                $val = ord($data[0]) & 0xff;
                $val <<= 8;
                $val |= ord($data[1]) & 0xff;
                $val <<= 8;
                $val |= ord($data[2]) & 0xff;
                $val <<= 8;
                $val |= ord($data[3]) & 0xff;
                $length = intval($val);
                return $length < 0 ? -1 : $length + 4;
            },
        ));
        return $client;
    }

    public static function createAsyncClient($linkId , RemotingClientAsyncListener $asyncObj){
        $client = new \Swoole\Client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC); //异步非阻塞
        $client->set(array(
            'enable_coroutine'      => true,
            'linkId'                => $linkId,
            'open_length_check'     => true,
            'package_length_type'   => 'N',
            'package_max_length'    => 1024*1024*2, //协议最大长度
            'package_length_func'   => function ($data) {
                if (strlen($data) < 8) {
                    return 0;
                }
                $val = ord($data[0]) & 0xff;
                $val <<= 8;
                $val |= ord($data[1]) & 0xff;
                $val <<= 8;
                $val |= ord($data[2]) & 0xff;
                $val <<= 8;
                $val |= ord($data[3]) & 0xff;
                $length = intval($val);
                return $length < 0 ? -1 : $length + 4;
            },
        ));
        $client->on("connect", [$asyncObj , "onConnect"]);
        $client->on("receive", [$asyncObj , "onReceive"]);
        $client->on("error", [$asyncObj , "onError"]);
        $client->on("close", [$asyncObj , "onClose"]);
        return $client;
    }
}