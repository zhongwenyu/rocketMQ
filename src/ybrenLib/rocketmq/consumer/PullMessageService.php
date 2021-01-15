<?php
namespace ybrenLib\rocketmq\consumer;

use ybrenLib\logger\Logger;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\core\Channel;
use ybrenLib\rocketmq\MQAsyncClientInstance;
use ybrenLib\rocketmq\util\CoroutineUtil;
use ybrenLib\rocketmq\util\TimeUtil;

class PullMessageService
{
    /**
     * @var Logger
     */
    private $log;
    /**
     * @var Channel
     */
    private $pullRequestQueue;

    /**
     * @var MQAsyncClientInstance
     */
    private $mqAsyncClientInstance;
    /**
     * 是否关机
     * @var bool
     */
    private $stopped = false;

    public function __construct(MQAsyncClientInstance $mqAsyncClientInstance){
        $this->pullRequestQueue = new Channel(1024);
        $this->mqAsyncClientInstance = $mqAsyncClientInstance;
        $this->log = LoggerFactory::getLogger(PullMessageService::class);

        // 启动数据拉取
        $this->run();
    }

    /**
     * @return bool
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * @param bool $stopped
     */
    public function setStopped(bool $stopped)
    {
        $this->stopped = $stopped;
    }

    public function executePullRequestLater(PullRequest $pullRequest, int $timeDelay) {
        if (!$this->isStopped()) {
            \swoole_timer_after($timeDelay, function ()use ($pullRequest){
                $this->executePullRequestImmediately($pullRequest);
            });
        } else {
            $this->log->warn("PullMessageServiceScheduledThread has shutdown");
        }
    }

    public function executePullRequestImmediately(PullRequest $pullRequest) {
        CoroutineUtil::run(function () use ($pullRequest) {
            if($this->isStopped()){
                return;
            }
            try {
                $pushResult = $this->pullRequestQueue->push($pullRequest);
                if($pushResult === false){
                    $this->executePullRequestLater($pullRequest , 3000);
                }
            } catch (\Exception $e) {
                $this->log->error("executePullRequestImmediately pullRequestQueue.put". " error: ".$e->getMessage());
            }
        });
    }

    public function run(){
        CoroutineUtil::run(function () {
            while (!$this->isStopped()){
                try{
                    $pullRequest = $this->pullRequestQueue->pop();
                    if($pullRequest !== false){
                        $this->pullMessage($pullRequest);
                    }
                }catch (\Exception $e){
                    // var_dump($e->getMessage());
                    $this->log->error("pull message error: ".$e->getMessage());
                }
            }
        });
    }

    private function pullMessage(PullRequest $pullRequest) {
        if($this->isStopped()){
            return;
        }
        $consumer = $this->mqAsyncClientInstance->selectConsumer($pullRequest->getConsumerGroup());
        if ($consumer != null) {
            $consumer->pullMessage($pullRequest);
        } else {
            $this->log->warn("No matched consumer for the PullRequest {}, drop it", json_encode($pullRequest));
        }
    }

    /**
     * 关机
     */
    public function shutdown(){
        $this->log->info("start to close pullMessageService");
        $this->setStopped(true);
        $this->pullRequestQueue->close();
    }
}