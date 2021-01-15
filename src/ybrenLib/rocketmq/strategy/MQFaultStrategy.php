<?php
namespace ybrenLib\rocketmq\strategy;

use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\entity\TopicPublishInfo;

class MQFaultStrategy
{

    /**
     * @var LatencyFaultTolerance
     */
    private $latencyFaultTolerance;

    public function __construct(){
        $this->latencyFaultTolerance = new LatencyFaultTolerance();
    }

    /**
     * @param TopicPublishInfo $tpInfo
     * @param $lastBrokerName
     * @return MessageQueue
     */
    public function selectOneMessageQueue(TopicPublishInfo $tpInfo, $lastBrokerName){
        $index = $tpInfo->getAndIncrSendWhichQueue();
        $messageQueueListNum = count($tpInfo->getMessageQueueList());
        for ($i = 0; $i < $messageQueueListNum; $i++) {
            $pos = abs($index++) % $messageQueueListNum;
            if ($pos < 0)
                $pos = 0;
            $mq = $tpInfo->getMessageQueueList()[$pos];
            if (null == $lastBrokerName || $mq->getBrokerName() != $lastBrokerName)
                // 选择一个broker
                return $mq;
        }
    }
}