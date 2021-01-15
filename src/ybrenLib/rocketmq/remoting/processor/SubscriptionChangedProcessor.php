<?php
namespace ybrenLib\rocketmq\remoting\processor;

use ybrenLib\rocketmq\MQAsyncClientInstance;
use ybrenLib\rocketmq\remoting\AbstractRemotingClient;
use ybrenLib\rocketmq\remoting\InvokeCallback;
use ybrenLib\rocketmq\remoting\RemotingCommand;

class SubscriptionChangedProcessor implements Processor
{
    /**
     * @var MQAsyncClientInstance
     */
    private $mqClientFactory;

    private $doRebalanceComplated = false;

    /**
     * SubscriptionChangedProcessor constructor.
     * @param MQAsyncClientInstance $mqClientFactory
     */
    public function __construct(MQAsyncClientInstance $mqClientFactory)
    {
        $this->mqClientFactory = $mqClientFactory;
    }

    function execute(AbstractRemotingClient $client , RemotingCommand $remotingCommand , InvokeCallback $invokeCallback = null)
    {
        if(!$this->doRebalanceComplated){
            $this->mqClientFactory->doRebalance();
            $this->doRebalanceComplated = true;
        }
    }

    function exception(\Exception $e)
    {
        // TODO: Implement exception() method.
    }

}