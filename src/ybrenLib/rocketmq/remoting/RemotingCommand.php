<?php
namespace ybrenLib\rocketmq\remoting;

use ybrenLib\rocketmq\core\AtomicLong;
use ybrenLib\rocketmq\core\Column;
use ybrenLib\rocketmq\remoting\header\CommandCustomHeader;

class RemotingCommand extends Column {

    /**
     * @var AtomicLong
     */
    protected static $requestId = null;
    protected $code;
    protected $language = "JAVA";
    protected $version = 355;
    protected $opaque;
    protected $flag = 0;
    protected $remark;
    protected $extFields = null;
    protected $body;

    /**
     * @var CommandCustomHeader
     */
    private $customHeader = null;
    private $serializeTypeCurrentRPC = "JSON";

    public function __construct($data = [])
    {
        parent::__construct($data);
        $this->opaque = self::init()->getAndIncrement();
    }

    /**
     * @param $code
     * @param CommandCustomHeader $customHeader
     * @return RemotingCommand
     */
    public static function createRequestCommand($code , CommandCustomHeader $customHeader = null){
        $remotingCommand = new RemotingCommand();
        $remotingCommand->setCode($code);
        $remotingCommand->setCustomHeader($customHeader);
        return $remotingCommand;
    }

    public function toArray(){
        if(!is_null($this->customHeader)){
            $exitFields = $this->customHeader->getHeader();
        }else{
            $exitFields = $this->extFields;
        }

        $data = [
            "code" => $this->code,
            "extFields" => $exitFields,
            "flag" => $this->flag,
            "language" => $this->language,
            "opaque" => $this->opaque,
            "serializeTypeCurrentRPC" => $this->serializeTypeCurrentRPC,
            "version" => $this->version
        ];
        if(!is_null($this->remark)){
            $data['remark'] = $this->remark;
        }
        return $data;
    }

    /**
     * @return AtomicLong
     */
    public static function init(){
        if(is_null(self::$requestId)){
            self::$requestId = new AtomicLong();
        }
        return self::$requestId;
    }

    /**
     * @return int
     */
    public static function getRequestId()
    {
        return self::$requestId;
    }

    /**
     * @param int $requestId
     */
    public static function setRequestId($requestId)
    {
        self::$requestId = $requestId;
    }

    /**
     * @return mixed
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param mixed $code
     */
    public function setCode($code)
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * @param string $language
     */
    public function setLanguage($language)
    {
        $this->language = $language;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param int $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getOpaque()
    {
        return $this->opaque;
    }

    /**
     * @param mixed $opaque
     */
    public function setOpaque($opaque)
    {
        $this->opaque = $opaque;
    }

    /**
     * @return int
     */
    public function getFlag()
    {
        return $this->flag;
    }

    /**
     * @param int $flag
     */
    public function setFlag($flag)
    {
        $this->flag = $flag;
    }

    /**
     * @return mixed
     */
    public function getRemark()
    {
        return $this->remark;
    }

    /**
     * @param mixed $remark
     */
    public function setRemark($remark)
    {
        $this->remark = $remark;
    }

    /**
     * @return mixed
     */
    public function getExtFields()
    {
        return $this->extFields;
    }

    /**
     * @param mixed $extFields
     */
    public function setExtFields($extFields)
    {
        $this->extFields = $extFields;
    }

    /**
     * @return CommandCustomHeader
     */
    public function getCustomHeader()
    {
        return $this->customHeader;
    }

    /**
     * @param CommandCustomHeader $customHeader
     */
    public function setCustomHeader($customHeader)
    {
        $this->customHeader = $customHeader;
    }

    /**
     * @return string
     */
    public function getSerializeTypeCurrentRPC()
    {
        return $this->serializeTypeCurrentRPC;
    }

    /**
     * @param string $serializeTypeCurrentRPC
     */
    public function setSerializeTypeCurrentRPC($serializeTypeCurrentRPC)
    {
        $this->serializeTypeCurrentRPC = $serializeTypeCurrentRPC;
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
}