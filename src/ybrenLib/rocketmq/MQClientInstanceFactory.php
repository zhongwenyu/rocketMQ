<?php
namespace ybrenLib\rocketmq;

use ybrenLib\rocketmq\cache\YacCache;

class MQClientInstanceFactory
{
    private static $_mqAsyncClientInstances = [];
    private static $_mqSyncClientInstances = [];

    /**
     * @return MQAsyncClientInstance|null
     */
    public static function createAsync($namesrvAddr){
        if(!isset(self::$_mqAsyncClientInstances[$namesrvAddr])){
            self::$_mqAsyncClientInstances[$namesrvAddr] = new MQAsyncClientInstance($namesrvAddr);
        }
        return self::$_mqAsyncClientInstances[$namesrvAddr];
    }

    /**
     * @return MQClientInstance|null
     */
    public static function createSync($namesrvAddr){
        if(!isset(self::$_mqSyncClientInstances[$namesrvAddr])){
            self::$_mqSyncClientInstances[$namesrvAddr] = new MQClientInstance($namesrvAddr);
        }
        return self::$_mqSyncClientInstances[$namesrvAddr];
    }

    public static function removeAsync($namesrvAddr){
        unset(self::$_mqAsyncClientInstances[$namesrvAddr]);
    }
}