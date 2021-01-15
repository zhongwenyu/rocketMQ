<?php
namespace ybrenLib\rocketmq\entity;

use ybrenLib\rocketmq\core\Column;

class Message extends Column
{
    // 主题
    protected $topic;
    protected $flag;
    // 扩展属性, delayTimeLevel 消息的延迟级别
    protected $properties;
    // 消息体
    protected $body;
    protected $transactionId;

    public function __construct($topic , $body , $tags = "", $keys = "", $flag = 0){
        $this->topic = $topic;
        $this->body = $body;
        $this->flag = $flag;
    }

    public function getTags() {
        return $this->getProperty(MessageConst::PROPERTY_TAGS);
    }

    public function setTags(string $tags) {
        $this->putProperty(MessageConst::PROPERTY_TAGS, $tags);
    }

    public function getKeys() {
        return $this->getProperty(MessageConst::PROPERTY_KEYS);
    }

    public function setKeys($keys) {
        $sb = "";
        if(is_array($keys)){
            foreach ($keys as $k) {
                $sb .= $k;
                $sb .= MessageConst::KEY_SEPARATOR;
            }
        }else{
            $sb = $keys;
        }
        $this->putProperty(MessageConst::PROPERTY_KEYS, trim($sb));
    }

    public function getDelayTimeLevel() {
        $t = $this->getProperty(MessageConst::PROPERTY_DELAY_TIME_LEVEL);
        if ($t != null) {
            return intval($t);
        }
        return 0;
    }

    public function setDelayTimeLevel($level) {
        $this->putProperty(MessageConst::PROPERTY_DELAY_TIME_LEVEL, $level);
    }

    public function putProperty(string $name, string $value) {
        if (null == $this->properties) {
            $this->properties = [];
        }

        $this->properties[$name] = $value;
    }

    public function clearProperty(string $name) {
        if (null != $this->properties) {
            unset($this->properties[$name]);
        }
    }

    public function putUserProperty(string $name, string $value) {
        if (in_array($name , MessageConst::$stringHashSet)) {
            throw new \Exception("The Property<".$name."> is used by system, input another please");
        }

        if ($value == null || empty(trim($value))
            || $name == null || empty(trim($name)) ){
            throw new \Exception(
                "The name or value of property can not be null or blank string!"
            );
        }

        $this->putProperty($name, $value);
    }

    public function getUserProperty(string $name) {
        return $this->getProperty($name);
    }

    public function getProperty(string $name) {
        if (null == $this->properties) {
            $this->properties = [];
        }

        return $this->properties[$name] ?? null;
    }

    /**
     * @return mixed
     */
    public function getTopic()
    {
        return $this->topic;
    }

    /**
     * @param mixed $topic
     */
    public function setTopic($topic)
    {
        $this->topic = $topic;
    }

    /**
     * @return mixed
     */
    public function getFlag()
    {
        return $this->flag;
    }

    /**
     * @param mixed $flag
     */
    public function setFlag($flag)
    {
        $this->flag = $flag;
    }

    /**
     * @return mixed
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param mixed $properties
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param mixed $body
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * @return mixed
     */
    public function getTransactionId()
    {
        return $this->transactionId;
    }

    /**
     * @param mixed $transactionId
     */
    public function setTransactionId($transactionId)
    {
        $this->transactionId = $transactionId;
    }

    /**
     * @param bool $waitStoreMsgOK
     */
    public function setWaitStoreMsgOK(bool $waitStoreMsgOK) {
        $this->putProperty(MessageConst::PROPERTY_WAIT_STORE_MSG_OK, $waitStoreMsgOK);
    }
}