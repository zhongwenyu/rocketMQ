<?php


namespace ybrenLib\rocketmq\remoting\heartbeat;


use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\util\TimeUtil;

class SubscriptionData extends Column
{
    const SUB_ALL = "*";
    protected $classFilterMode = false;
    protected $topic;
    protected $subString;
    protected $tagsSet = [];
    protected $codeSet = [];
    protected $subVersion;
    protected $expressionType = "TAG";

    public function __construct($data = []){
        $this->subVersion = TimeUtil::currentTimeMillis();
        parent::__construct($data);
    }

    /**
     * @return bool
     */
    public function isClassFilterMode(): bool
    {
        return $this->classFilterMode;
    }

    /**
     * @param bool $classFilterMode
     */
    public function setClassFilterMode(bool $classFilterMode)
    {
        $this->classFilterMode = $classFilterMode;
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
    public function getSubString()
    {
        return $this->subString;
    }

    /**
     * @param mixed $subString
     */
    public function setSubString($subString)
    {
        $this->subString = $subString;
    }

    /**
     * @return mixed
     */
    public function getTagsSet()
    {
        return $this->tagsSet;
    }

    /**
     * @param array $tagsSet
     */
    public function setTagsSet(array $tagsSet)
    {
        $this->tagsSet = $tagsSet;
    }

    /**
     * @return array
     */
    public function getCodeSet()
    {
        return $this->codeSet;
    }

    /**
     * @param array $codeSet
     */
    public function setCodeSet(array $codeSet)
    {
        $this->codeSet = $codeSet;
    }

    /**
     * @return int
     */
    public function getSubVersion(): int
    {
        return $this->subVersion;
    }

    /**
     * @param int $subVersion
     */
    public function setSubVersion(int $subVersion)
    {
        $this->subVersion = $subVersion;
    }

    /**
     * @return string
     */
    public function getExpressionType(): string
    {
        return $this->expressionType;
    }

    /**
     * @param string $expressionType
     */
    public function setExpressionType(string $expressionType)
    {
        $this->expressionType = $expressionType;
    }
}