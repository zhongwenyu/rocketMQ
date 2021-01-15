<?php
namespace ybrenLib\rocketmq\remoting\processor;

use ybrenLib\rocketmq\consumer\PullResultExt;
use ybrenLib\rocketmq\entity\PullStatus;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\remoting\AbstractRemotingClient;
use ybrenLib\rocketmq\remoting\InvokeCallback;
use ybrenLib\rocketmq\remoting\RemotingCommand;
use ybrenLib\rocketmq\remoting\ResponseCode;

class PullMessageProcessor implements Processor
{

    private function processPullResponse(RemotingCommand $response){
        $pullStatus = PullStatus::NO_NEW_MSG;
        switch ($response->getCode()) {
            case ResponseCode::$SUCCESS:
                $pullStatus = PullStatus::FOUND;
                break;
            case ResponseCode::$PULL_NOT_FOUND:
                $pullStatus = PullStatus::NO_NEW_MSG;
                break;
            case ResponseCode::$PULL_RETRY_IMMEDIATELY:
                $pullStatus = PullStatus::NO_MATCHED_MSG;
                break;
            case ResponseCode::$PULL_OFFSET_MOVED:
                $pullStatus = PullStatus::OFFSET_ILLEGAL;
                break;
            default:
                throw new RocketMQClientException($response->getCode(), $response->getRemark());
        }

    }

    function execute(AbstractRemotingClient $client, RemotingCommand $response , InvokeCallback $invokeCallback = null)
    {



    }

    function exception(\Exception $e)
    {
        // TODO: Implement exception() method.
    }
}