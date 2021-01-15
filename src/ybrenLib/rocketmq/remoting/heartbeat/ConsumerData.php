<?php


namespace ybrenLib\rocketmq\remoting\heartbeat;


use ybrenLib\rocketmq\consumer\ConsumeFromWhere;
use ybrenLib\rocketmq\consumer\MQConsumerInner;
use ybrenLib\rocketmq\core\Column;

class ConsumerData extends Column
{
    protected $groupName;
    protected $consumeType;
    protected $messageModel = MessageModel::CLUSTERING;
    protected $consumeFromWhere = ConsumeFromWhere::CONSUME_FROM_LAST_OFFSET;
    /**
     * @var SubscriptionData[]
     */
    protected $subscriptionDataSet = [];
    protected $unitMode = false;

    public function __construct(MQConsumerInner $mqConsumerInner)
    {
        $this->setGroupName($mqConsumerInner->groupName());
        $this->setConsumeType($mqConsumerInner->consumeType());
        $this->setMessageModel($mqConsumerInner->messageModel());
        $this->setConsumeFromWhere($mqConsumerInner->consumeFromWhere());
        $this->setSubscriptionDataSet($mqConsumerInner->subscriptions());
        $this->setUnitMode($mqConsumerInner->isUnitMode());
        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @param mixed $groupName
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;
    }

    /**
     * @return mixed
     */
    public function getConsumeType()
    {
        return $this->consumeType;
    }

    /**
     * @param mixed $consumeType
     */
    public function setConsumeType($consumeType)
    {
        $this->consumeType = $consumeType;
    }

    /**
     * @return string
     */
    public function getMessageModel(): string
    {
        return $this->messageModel;
    }

    /**
     * @param string $messageModel
     */
    public function setMessageModel(string $messageModel)
    {
        $this->messageModel = $messageModel;
    }

    /**
     * @return string
     */
    public function getConsumeFromWhere(): string
    {
        return $this->consumeFromWhere;
    }

    /**
     * @param string $consumeFromWhere
     */
    public function setConsumeFromWhere(string $consumeFromWhere)
    {
        $this->consumeFromWhere = $consumeFromWhere;
    }

    /**
     * @return SubscriptionData[]
     */
    public function getSubscriptionDataSet(): array
    {
        return $this->subscriptionDataSet;
    }

    /**
     * @param array $subscriptionDataSet
     */
    public function setSubscriptionDataSet(array $subscriptionDataSet)
    {
        $this->subscriptionDataSet = $subscriptionDataSet;
    }

    /**
     * @return bool
     */
    public function isUnitMode(): bool
    {
        return $this->unitMode;
    }

    /**
     * @param bool $unitMode
     */
    public function setUnitMode(bool $unitMode)
    {
        $this->unitMode = $unitMode;
    }
}