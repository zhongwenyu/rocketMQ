<?php
namespace ybrenLib\rocketmq\consumer;

class PullResultExt extends PullResult
{
    protected $suggestWhichBrokerId;
    protected $messageBinary;

    public function __construct($pullStatus, $nextBeginOffset, $minOffset, $maxOffset, $msgFoundList, $suggestWhichBrokerId, $messageBinary) {
        parent::__construct($pullStatus, $nextBeginOffset, $minOffset, $maxOffset, $msgFoundList);
        $this->suggestWhichBrokerId = $suggestWhichBrokerId;
        $this->messageBinary = $messageBinary;
    }

    /**
     * @return mixed
     */
    public function getSuggestWhichBrokerId()
    {
        return $this->suggestWhichBrokerId;
    }

    /**
     * @return mixed
     */
    public function getMessageBinary()
    {
        return $this->messageBinary;
    }

    /**
     * @param mixed $messageBinary
     */
    public function setMessageBinary($messageBinary)
    {
        $this->messageBinary = $messageBinary;
    }
}