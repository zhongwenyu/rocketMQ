<?php
namespace ybrenLib\rocketmq\consumer;

use Closure;
use ybrenLib\rocketmq\remoting\callback\InvokeCallback;
use ybrenLib\rocketmq\remoting\RemotingCommand;

class PullMessageCallback implements InvokeCallback
{
    /**
     * @var Closure
     */
    private $pullback;

    public function __construct(Closure $pullback){
        $this->pullback = $pullback;
    }

    function operationComplete(RemotingCommand $response){
        $pullback = $this->pullback;
        $pullback($response);
    }
}