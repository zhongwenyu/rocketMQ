<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\core\AtomicLong;
use ybrenLib\rocketmq\core\Lock;
use ybrenLib\rocketmq\core\ReadWriteLock;
use ybrenLib\rocketmq\core\TreeMap;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\util\TimeUtil;

class ProcessQueue
{
    /**
     * @var Logger
     */
    private $log;
    private static $REBALANCE_LOCK_MAX_LIVE_TIME = 30000;
    /**
     * 消息存储容器，键名为消息在ComsumerQueue中的偏移量，该结构用于处理顺序消息
     * @var TreeMap
     */
    private $msgTreeMap;
    // 消息总数
    private $msgCount;
    private $msgSize;
    /**
     * @var TreeMap
     */
    private $consumingMsgOrderlyTreeMap;
    private $tryUnlockTimes = 0;
    // ProcessQueue包含的最大队列偏移量
    private $queueOffsetMax = 0;
    // 上一次开始拉取消息时间戳
    private $lastPullTimestamp;
    // 上一次消息消费时间戳
    private $lastConsumeTimestamp;
    private $locked = false;
    private $consuming = false;
    private $msgAccCnt = 0;
    /**
     * @var Lock
     */
    private $lockConsume;

    /**
     * @var ReadWriteLock
     */
    private $lockTreeMap;

    // ProcessQueue是否被丢弃
    private $dropped = false;
    private $lastLockTimestamp;

    public function __construct(){
        $this->log = LoggerFactory::getLogger(ProcessQueue::class);
        $this->msgTreeMap = TreeMap::create();
        $this->consumingMsgOrderlyTreeMap = TreeMap::create();
        $this->lastPullTimestamp = TimeUtil::currentTimeMillis();
        $this->lastConsumeTimestamp = TimeUtil::currentTimeMillis();
        $this->lockTreeMap = new ReadWriteLock();
        $this->msgCount = new AtomicLong();
        $this->msgSize = new AtomicLong();
        $this->lockConsume = new Lock();
        $this->lastConsumeTimestamp = TimeUtil::currentTimeMillis();
    }

    /**
     * 添加消息，PullMessageService拉取消息后，先调用这个方法放到ProcessQueue
     * @param MessageExt[]
     * @return
     */
    public function putMessage($msgs) {
        $dispatchToConsume = false;
        try {
            $this->lockTreeMap->writeLock(2);
            try {
                $validMsgCnt = 0;
                foreach ($msgs as $msg) {
                    $old = $this->msgTreeMap->put($msg->getQueueOffset(), $msg);
                    if (null == $old) {
                        $validMsgCnt++;
                        $this->queueOffsetMax = $msg->getQueueOffset();
                        $this->msgSize->getAndIncrement(strlen($msg->getBody()));
                    }
                }
                $this->msgCount->getAndIncrement($validMsgCnt);

                if (!empty($this->msgTreeMap) && !$this->consuming) {
                    $dispatchToConsume = true;
                    $this->consuming = true;
                }

                if (!empty($msgs)) {
                    $messageExt = $msgs[count($msgs) - 1];
                    $property = $messageExt->getProperty(MessageConst::PROPERTY_MAX_OFFSET);
                    if ($property != null) {
                        $accTotal = $property - $messageExt->getQueueOffset();
                        if ($accTotal > 0) {
                            $this->msgAccCnt = $accTotal;
                        }
                    }
                }
            } finally {
                $this->lockTreeMap->releaseWriteLock();
            }
        } catch (\Exception $e) {
            $this->log->error("putMessage exception: " .  $e->getMessage());
        }

        return $dispatchToConsume;
    }

    /**
     * @return bool
     */
    public function hasTempMessage() {
        if(!$this->lockTreeMap->readLock(2)){
            // 加锁失败
            return true;
        }
        try{
            return !empty($this->msgTreeMap);
        } finally {
            $this->lockTreeMap->releaseReadLock();
        }
    }

    /**
     * 移除消息
     * @param MessageExt[] $msgs
     * @return
     */
    public function removeMessage(array $msgs) {
        $result = -1;
        $now = TimeUtil::currentTimeMillis();
        try {
            $this->lockTreeMap->writeLock(2);
            $this->lastConsumeTimestamp = $now;
            try {
                if (!empty($this->msgTreeMap)) {
                    $result = $this->queueOffsetMax + 1;
                    $removedCnt = 0;
                    foreach ($msgs as $msg) {
                        $prev = $this->msgTreeMap->remove($msg->getQueueOffset());
                        if ($prev != null) {
                            $removedCnt--;
                            $this->msgSize->getAndIncrement(0 - strlen($msg->getBody()));
                        }
                    }
                    $this->msgCount->getAndIncrement($removedCnt);

                    if (!empty($this->msgTreeMap)) {
                        $result = $this->msgTreeMap->firstKey();
                    }
                }
            } finally {
                $this->lockTreeMap->releaseWriteLock();
            }
        } catch (\Throwable $t) {
            $this->log->error("removeMessage exception".$t->getMessage());
        }

        return $result;
    }

    /**
     * 将consumingMsgOrderlyTreeMap清空，表示消息处理成功
     * @return
     */
    public function commit() {
        try {
            $this->lockTreeMap->writeLock(2);
            try {
                $offset = $this->consumingMsgOrderlyTreeMap->lastKey();
                $this->msgCount->getAndIncrement(0 - count($this->consumingMsgOrderlyTreeMap));
                foreach ($this->consumingMsgOrderlyTreeMap as $msg) {
                    $this->msgSize->getAndIncrement(0 - strlen($msg->getBody()));
                }
                $this->consumingMsgOrderlyTreeMap->clear();
                if ($offset != null) {
                    return $offset + 1;
                }
            } finally {
                $this->lockTreeMap->releaseWriteLock();
            }
        } catch (\Exception $e) {
            $this->log->error("commit exception: ". $e->getMessage());
        }

        return -1;
    }

    /**
     * @param $batchSize
     * @return MessageExt[] $result
     */
    public function takeMessages($batchSize) {
        $result = [];
        $now = TimeUtil::currentTimeMillis();
        $this->lockTreeMap->writeLock(2);
        try {
            $this->lastConsumeTimestamp = $now;
            if (!empty($this->msgTreeMap)) {
                for ($i = 0; $i < $batchSize; $i++) {
                    try {
                        $firstKey = $this->msgTreeMap->firstKey();
                        if ($firstKey != null) {
                            $value = $this->msgTreeMap->remove($firstKey);
                            $result[] = $value;
                            $this->consumingMsgOrderlyTreeMap->put($firstKey, $value);
                        } else {
                            break;
                        }
                    } catch (\Exception $e) {
                        break;
                    }
                }
            }
        } finally {
            $this->lockTreeMap->releaseWriteLock();
        }

        if (empty($result)) {
            $this->consuming = false;
        }

        return $result;
    }

    /**
     * @param MessageExt[] $msgs
     */
    public function makeMessageToCosumeAgain($msgs) {
        try {
            $this->lockTreeMap->writeLock(2);
            try {
                foreach ($msgs as $msg) {
                    $this->consumingMsgOrderlyTreeMap->remove($msg->getQueueOffset());
                    $this->msgTreeMap->put($msg->getQueueOffset(), $msg);
                }
            } finally {
                $this->lockTreeMap->releaseWriteLock();
            }
        } catch (\Exception $e) {
            $this->log->error("makeMessageToCosumeAgain exception: ". $e->getMessage());
        }
    }

    /**
     * 判断锁是否过期
     * @return bool
     */
    public function isLockExpired() {
        return (TimeUtil::currentTimeMillis() - $this->lastLockTimestamp) > self::$REBALANCE_LOCK_MAX_LIVE_TIME;
    }

    /**
     * 获取当前消息最大间隔
     * @return
     */
    public function getMaxSpan() {
        if ($this->msgTreeMap->count() > 0) {
            return $this->msgTreeMap->lastKey() - $this->msgTreeMap->firstKey();
        }
        return 0;
    }

    /**
     * @return bool
     */
    public function isDropped(): bool
    {
        return $this->dropped;
    }

    /**
     * @param bool $dropped
     */
    public function setDropped(bool $dropped)
    {
        $this->dropped = $dropped;
    }

    /**
     * @return TreeMap
     */
    public function getMsgTreeMap(): TreeMap
    {
        return $this->msgTreeMap;
    }

    /**
     * @param TreeMap $msgTreeMap
     */
    public function setMsgTreeMap(TreeMap $msgTreeMap)
    {
        $this->msgTreeMap = $msgTreeMap;
    }

    /**
     * @return AtomicLong
     */
    public function getMsgCount(): AtomicLong
    {
        return $this->msgCount;
    }

    /**
     * @param AtomicLong $msgCount
     */
    public function setMsgCount(AtomicLong $msgCount)
    {
        $this->msgCount = $msgCount;
    }

    /**
     * @return AtomicLong
     */
    public function getMsgSize(): AtomicLong
    {
        return $this->msgSize;
    }

    /**
     * @param AtomicLong $msgSize
     */
    public function setMsgSize(AtomicLong $msgSize)
    {
        $this->msgSize = $msgSize;
    }

    /**
     * @return TreeMap
     */
    public function getConsumingMsgOrderlyTreeMap(): TreeMap
    {
        return $this->consumingMsgOrderlyTreeMap;
    }

    /**
     * @param TreeMap $consumingMsgOrderlyTreeMap
     */
    public function setConsumingMsgOrderlyTreeMap(TreeMap $consumingMsgOrderlyTreeMap)
    {
        $this->consumingMsgOrderlyTreeMap = $consumingMsgOrderlyTreeMap;
    }

    /**
     * @return int
     */
    public function getTryUnlockTimes(): int
    {
        return $this->tryUnlockTimes;
    }

    /**
     * @param int $tryUnlockTimes
     */
    public function setTryUnlockTimes(int $tryUnlockTimes)
    {
        $this->tryUnlockTimes = $tryUnlockTimes;
    }

    /**
     * @return int
     */
    public function getQueueOffsetMax(): int
    {
        return $this->queueOffsetMax;
    }

    /**
     * @param int $queueOffsetMax
     */
    public function setQueueOffsetMax(int $queueOffsetMax)
    {
        $this->queueOffsetMax = $queueOffsetMax;
    }

    /**
     * @return int
     */
    public function getLastPullTimestamp(): int
    {
        return $this->lastPullTimestamp;
    }

    /**
     * @param int $lastPullTimestamp
     */
    public function setLastPullTimestamp(int $lastPullTimestamp)
    {
        $this->lastPullTimestamp = $lastPullTimestamp;
    }

    /**
     * @return int
     */
    public function getLastConsumeTimestamp(): int
    {
        return $this->lastConsumeTimestamp;
    }

    /**
     * @param int $lastConsumeTimestamp
     */
    public function setLastConsumeTimestamp(int $lastConsumeTimestamp)
    {
        $this->lastConsumeTimestamp = $lastConsumeTimestamp;
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }

    /**
     * @param bool $locked
     */
    public function setLocked(bool $locked)
    {
        $this->locked = $locked;
    }

    /**
     * @return bool
     */
    public function isConsuming(): bool
    {
        return $this->consuming;
    }

    /**
     * @param bool $consuming
     */
    public function setConsuming(bool $consuming)
    {
        $this->consuming = $consuming;
    }

    /**
     * @return int
     */
    public function getMsgAccCnt(): int
    {
        return $this->msgAccCnt;
    }

    /**
     * @param int $msgAccCnt
     */
    public function setMsgAccCnt(int $msgAccCnt)
    {
        $this->msgAccCnt = $msgAccCnt;
    }

    /**
     * @return Lock
     */
    public function getLockConsume(): Lock
    {
        return $this->lockConsume;
    }

    /**
     * 判断PullMessageService是否空闲，默认120s
     * @return
     */
    public function isPullExpired() {
        return (TimeUtil::currentTimeMillis() - $this->lastPullTimestamp) > 120*1000;
    }
}