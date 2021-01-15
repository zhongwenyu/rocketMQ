<?php
namespace ybrenLib\rocketmq\util;

use ybrenLib\rocketmq\remoting\heartbeat\SubscriptionData;

class FilterApi
{
    /**
     * @param string $consumerGroup
     * @param string $topic
     * @param string $subString
     * @return SubscriptionData
     * @throws \Exception
     */
    public static function buildSubscriptionData(string $consumerGroup, string $topic, string $subString){
        $subscriptionData = new SubscriptionData();
        $subscriptionData->setTopic($topic);
        $subscriptionData->setSubString($subString);

        if (empty($subString) || $subString == SubscriptionData::SUB_ALL) {
            $subscriptionData->setSubString(SubscriptionData::SUB_ALL);
        } else {
            $tags = explode("\\|\\|" , $subString);
            if (count($tags) > 0) {
                foreach ($tags as $tag) {
                    if (!empty($tag)) {
                        $trimString = trim($tag);
                        if (!empty($trimString)) {
                            $subscriptionData->addTagsSet($trimString);
                            $subscriptionData->addCodeSet(md5($trimString));
                        }
                    }
                }
            } else {
                throw new \Exception("subString split error");
            }
        }

        return $subscriptionData;
    }
}