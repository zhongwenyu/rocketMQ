<?php
namespace ybrenLib\rocketmq\remoting;

use ybrenLib\rocketmq\core\ResponseFuture;
use ybrenLib\rocketmq\remoting\callback\InvokeCallback;

abstract class AbstractRemotingClient
{
    protected $addr;

    protected $client = null;

    abstract function connect();

    abstract function isConnected();

    abstract function send(RemotingCommand $remotingCommand , ResponseFuture $responseFuture = null);

    abstract function close();

    abstract function getAddr();
}