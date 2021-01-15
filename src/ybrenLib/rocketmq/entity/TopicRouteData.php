<?php
namespace ybrenLib\rocketmq\entity;

class TopicRouteData{
    // 顺序消息配置内容，来自于kvConfig
    private $orderTopicConf;
    /**
     * topic队列元数据
     * @var QueueData[]
     */
    private $queueDatas;

    /**
     * // topic分布的broker元数据
     * @var BrokerData[]
     */
    private $brokerDatas = [];
    // topic上过滤服务器列表
    private $filterServerTable;

    public function __construct($data){
        if(!empty($data['brokerDatas'])){
            foreach ($data['brokerDatas'] as $item){
                $this->brokerDatas[] = new BrokerData($item);
            }
        }
        if(!empty($data['queueDatas'])){
            foreach ($data['queueDatas'] as $item){
                $this->queueDatas[] = new QueueData($item);
            }
        }
        $this->filterServerTable = $data['filterServerTable'] ?? [];
    }

    /**
     * @return array|mixed
     */
    public function getQueueDatas()
    {
        return $this->queueDatas;
    }

    /**
     * @param array|mixed $queueDatas
     */
    public function setQueueDatas($queueDatas)
    {
        $this->queueDatas = $queueDatas;
    }

    /**
     * @return BrokerData[]
     */
    public function getBrokerDatas()
    {
        return $this->brokerDatas;
    }

    /**
     * @param BrokerData[] $brokerDatas
     */
    public function setBrokerDatas($brokerDatas)
    {
        $this->brokerDatas = $brokerDatas;
    }

    /**
     * @return array|mixed
     */
    public function getFilterServerTable()
    {
        return $this->filterServerTable;
    }

    /**
     * @param array|mixed $filterServerTable
     */
    public function setFilterServerTable($filterServerTable)
    {
        $this->filterServerTable = $filterServerTable;
    }

    /**
     * @return mixed
     */
    public function getOrderTopicConf()
    {
        return $this->orderTopicConf;
    }

    /**
     * @param mixed $orderTopicConf
     */
    public function setOrderTopicConf($orderTopicConf)
    {
        $this->orderTopicConf = $orderTopicConf;
    }
}