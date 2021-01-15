<?php
namespace ybrenLib\rocketmq\entity;

class TopicPublishInfo
{
    // 是否顺序消息
    private $orderTopic = false;
    private $haveTopicRouterInfo = false;
    // 该主题的队列的消息队列
    private $messageQueueList = [];
    // 每选择一次队列，该值自增1，用于选择消息队列
    private static $sendWhichQueue = 0;

    /**
     * @var TopicRouteData
     */
    private $topicRouteData;

    /**
     * @param null $lastBrokerName
     * @return MessageQueue
     */
    public function selectOneMessageQueue($lastBrokerName = null) {
        $messageQueueListSize = count($this->messageQueueList);
        if(empty($lastBrokerName)){
            $index = $this->getAndIncrSendWhichQueue();
            $pos = abs($index) % $messageQueueListSize;
            if ($pos < 0)
                $pos = 0;
            return $this->messageQueueList[$pos];
        }else{
            $index = $this->getAndIncrSendWhichQueue();
            for ($i = 0; $i < $messageQueueListSize; $i++) {
                $pos = abs($index++) % $messageQueueListSize;
                if ($pos < 0)
                    $pos = 0;
                $mq = $this->messageQueueList[$pos];
                if (!$mq->getBrokerName() == $lastBrokerName) {
                    return $mq;
                }
            }
            return $this->selectOneMessageQueue();
        }
    }

    public function addMessageQueueToList(MessageQueue $messageQueue){
        $this->messageQueueList[] = $messageQueue;
    }

    /**
     * @return int
     */
    public function getAndIncrSendWhichQueue()
    {
        if(++self::$sendWhichQueue > 10000*10000){
            self::$sendWhichQueue = 0;
        }
        return self::$sendWhichQueue;
    }

    /**
     * @return bool
     */
    public function isOrderTopic()
    {
        return $this->orderTopic;
    }

    /**
     * @param bool $orderTopic
     */
    public function setOrderTopic($orderTopic)
    {
        $this->orderTopic = $orderTopic;
    }

    /**
     * @return bool
     */
    public function isHaveTopicRouterInfo()
    {
        return $this->haveTopicRouterInfo;
    }

    /**
     * @param bool $haveTopicRouterInfo
     */
    public function setHaveTopicRouterInfo($haveTopicRouterInfo)
    {
        $this->haveTopicRouterInfo = $haveTopicRouterInfo;
    }


    /**
     * @return array
     */
    public function getMessageQueueList()
    {
        return $this->messageQueueList;
    }

    /**
     * @param array $messageQueueList
     */
    public function setMessageQueueList($messageQueueList)
    {
        $this->messageQueueList = $messageQueueList;
    }

    /**
     * @return int
     */
    public function getSendWhichQueue()
    {
        return self::$sendWhichQueue;
    }

    /**
     * @param int $sendWhichQueue
     */
    public function setSendWhichQueue($sendWhichQueue)
    {
        $this->sendWhichQueue = $sendWhichQueue;
    }

    /**
     * @return TopicRouteData
     */
    public function getTopicRouteData()
    {
        return $this->topicRouteData;
    }

    /**
     * @param TopicRouteData $topicRouteData
     */
    public function setTopicRouteData($topicRouteData)
    {
        $this->topicRouteData = $topicRouteData;
    }
}