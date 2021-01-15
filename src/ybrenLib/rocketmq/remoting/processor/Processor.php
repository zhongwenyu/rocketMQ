<?php
namespace ybrenLib\rocketmq\remoting\processor;

use ybrenLib\rocketmq\remoting\AbstractRemotingClient;
use ybrenLib\rocketmq\remoting\InvokeCallback;
use ybrenLib\rocketmq\remoting\RemotingCommand;

interface Processor
{
    function execute(AbstractRemotingClient $client , RemotingCommand $remotingCommand);

    function exception(\Exception $e);
}