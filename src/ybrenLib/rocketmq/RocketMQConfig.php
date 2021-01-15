<?php
namespace ybrenLib\rocketmq;

use ybrenLib\rocketmq\cache\Cache;

class RocketMQConfig
{
    private static $config = null;

    /**
     * @var Cache
     */
    private static $cache = null;

    /**
     * @return Cache|null
     */
    public static function getCache(){
        if(is_null(self::$cache)){
            $config = self::getConfig();
            $cacheInstanceClass = $config['cache'] ?? "\\ybrenLib\\rocketmq\\cache\\YacCache";
            self::$cache = new $cacheInstanceClass();
        }
        return self::$cache;
    }

    /**
     * @return array
     */
    public static function getConfig(){
        if(!is_null(self::$config)){
            return self::$config;
        }

        if(defined("ROOT_PATH") && is_file(ROOT_PATH . DIRECTORY_SEPARATOR . "rocketmq.json")){
            self::$config = json_decode(file_get_contents(ROOT_PATH . DIRECTORY_SEPARATOR . "rocketmq.json") , true);
        }

        return self::$config;
    }
}