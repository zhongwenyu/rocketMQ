<?php
namespace ybrenLib\rocketmq\stat;

class ConsumerStatsManager
{
    private static $TOPIC_AND_GROUP_CONSUME_OK_TPS = "CONSUME_OK_TPS";
    private static $TOPIC_AND_GROUP_CONSUME_FAILED_TPS = "CONSUME_FAILED_TPS";
    private static $TOPIC_AND_GROUP_CONSUME_RT = "CONSUME_RT";
    private static $TOPIC_AND_GROUP_PULL_TPS = "PULL_TPS";
    private static $TOPIC_AND_GROUP_PULL_RT = "PULL_RT";

    /**
     * @var StatsItemSet
     */
    private $topicAndGroupConsumeOKTPS;
    /**
     * @var StatsItemSet
     */
    private $topicAndGroupConsumeRT;
    /**
     * @var StatsItemSet
     */
    private $topicAndGroupConsumeFailedTPS;
    /**
     * @var StatsItemSet
     */
    private $topicAndGroupPullTPS;
    /**
     * @var StatsItemSet
     */
    private $topicAndGroupPullRT;

    public function incPullRT(final String group, final String topic, final long rt) {
        this.topicAndGroupPullRT.addRTValue(topic + "@" + group, (int) rt, 1);
    }
}