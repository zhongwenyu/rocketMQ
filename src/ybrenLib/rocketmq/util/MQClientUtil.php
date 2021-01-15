<?php
namespace ybrenLib\rocketmq\util;
use ybrenLib\rocketmq\entity\MessageQueue;
use ybrenLib\rocketmq\entity\PermName;
use ybrenLib\rocketmq\entity\TopicPublishInfo;
use ybrenLib\rocketmq\entity\TopicRouteData;
use ybrenLib\rocketmq\MQConstants;

class MQClientUtil
{
    public static $PERM_WRITE = 0x1 << 1;

    /**
     * @param $topic
     * @param TopicRouteData $route
     * @return TopicPublishInfo
     */
    public static function topicRouteData2TopicPublishInfo($topic, TopicRouteData $route) {
        $info = new TopicPublishInfo();
        $info->setTopicRouteData($route);
        if ($route->getOrderTopicConf() != null && strlen($route->getOrderTopicConf()) > 0) {
            $brokers = explode(";",$route->getOrderTopicConf());
            foreach ($brokers as $broker) {
                $item = explode(":" , $broker);
                $nums = intval($item[1]);
                for ($i = 0; $i < $nums; $i++) {
                    $mq = new MessageQueue($topic, $item[0], $i);
                    $info->addMessageQueueToList($mq);
                }
            }

            $info->setOrderTopic(true);
        } else {
            $qds = $route->getQueueDatas();
            self::arraySort($qds , "brokerName");
            foreach ($qds as $qd) {
                if (self::isWriteable($qd['perm'])) {
                    $brokerData = null;
                    foreach ($route->getBrokerDatas() as $bd) {
                        if ($bd->getBrokerName() == $qd['brokerName']) {
                            $brokerData = $bd;
                            break;
                        }
                    }

                    if (null == $brokerData) {
                        continue;
                    }

                    $brokerAddrs = $brokerData->getBrokerAddrs();
                    if (!isset($brokerAddrs[MQConstants::MASTER_ID])) {
                        continue;
                    }

                    for ($i = 0; $i < $qd['writeQueueNums']; $i++) {
                        $mq = new MessageQueue($topic, $qd['brokerName'], $i);
                        $info->addMessageQueueToList($mq);
                    }
                }
            }

            $info->setOrderTopic(false);
        }

        return $info;
    }

    /**
     * @param string $topic
     * @param TopicRouteData $route
     * @return MessageQueue[]
     */
    public static function topicRouteData2TopicSubscribeInfo(string $topic, TopicRouteData $route) {
        $mqList = [];
        $qds = $route->getQueueDatas();
        if(!empty($qds)){
            foreach ($qds as $qd) {
                if (PermName::isReadable($qd->getPerm())) {
                    for ($i = 0; $i < $qd->getReadQueueNums(); $i++) {
                        $mq = new MessageQueue($topic, $qd->getBrokerName(), $i);
                        $mqList[] = $mq;
                    }
                }
            }
        }

        return $mqList;
    }

    public static function arraySort($array,$keys,$sort='asc') {
        $newArr = $valArr = array();
        foreach ($array as $key=>$value) {
            $valArr[$key] = $value[$keys];
        }
        ($sort == 'asc') ?  asort($valArr) : arsort($valArr);
        reset($valArr);
        foreach($valArr as $key=>$value) {
            $newArr[$key] = $array[$key];
        }
        return $newArr;
    }

    public static function isWriteable($perm) {
        return ($perm & self::$PERM_WRITE) == self::$PERM_WRITE;
    }

    /**
     * @param $bodyJson
     * @return array
     */
    public static function formatBodyJson($bodyJson){
        for($i = 0;$i < 5;$i++){
            $originStr = "".$i.":\"";
            $replaceStr = "\"".$i."\":\"";
            if(strpos($bodyJson , $originStr) !== false){
                $bodyJson = str_replace($originStr , $replaceStr , $bodyJson);
            }
        }
        return json_decode($bodyJson , true);
    }

    public static function createMessageKey($topic){
        $chars = md5(uniqid(mt_rand(), true) . $topic);
        $nonceStr = rand(1000 , 9999);
        return strtoupper($chars).$nonceStr;
    }
}