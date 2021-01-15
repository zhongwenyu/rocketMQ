<?php
namespace ybrenLib\rocketmq\remoting\callback;

use ybrenLib\rocketmq\remoting\AbstractRemotingClient;
use ybrenLib\rocketmq\remoting\RemotingCommand;

interface InvokeCallback
{
    function operationComplete(RemotingCommand $remotingCommand);
}