<?php
namespace ybrenLib\rocketmq\core;

class ReadWriteLock
{
    /**
     * @var Lock
     */
    private $readLock;
    /**
     * @var Lock
     */
    private $writeLock;

    public function __construct(){
        $this->readLock = new Lock();
        $this->writeLock = new Lock();
    }

    /**
     * @param int $timeout
     * @return bool
     */
    public function writeLock($timeout = -1){
        return $this->writeLock->tryLock($timeout);
    }

    /**
     * @param int $timeout
     * @return bool
     */
    public function readLock($timeout = -1){
        return $this->readLock->tryLock($timeout);
    }

    public function releaseWriteLock(){
        $this->writeLock->release();
    }

    public function releaseReadLock(){
        $this->writeLock->release();
    }
}