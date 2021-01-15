<?php
namespace ybrenLib\rocketmq\core;

use ybrenLib\rocketmq\remoting\callback\InvokeCallback;
use ybrenLib\rocketmq\remoting\RemotingCommand;

class ResponseFuture
{
    private $lock;
    /**
     * @var RemotingCommand
     */
    private $response;
    /**
     * @var \Throwable
     */
    private $e = null;
    private $opaque;
    /**
     * @var InvokeCallback
     */
    private $invokeCallback;
    private $executeCallbackOnlyOnce = true;

    public function __construct(int $opaque , InvokeCallback $invokeCallback = null){
   //     $this->lock = new Lock(0);
        $this->opaque = $opaque;
        $this->invokeCallback = $invokeCallback;
    }

    public function setResponse(RemotingCommand $response){
        $this->response = $response;
    }

    public function setCause(\Throwable $e){
        $this->e = $e;
    }

    public function setResponseOk(){
    //    $this->lock->release();
    }

    /**
     * 执行回调方法
     */
    public function executeInvokeCallback(){
        if($this->invokeCallback != null && $this->executeCallbackOnlyOnce){
            $this->executeCallbackOnlyOnce = false;
            $this->invokeCallback->operationComplete($this->response);
        }
    }

    /**
     * @param int $timeout
     * @return RemotingCommand
     * @throws \Throwable
     */
    public function getResponse($timeout = -1){
        /*if($this->lock->tryLock($timeout)){
            if($this->e != null){
                throw $this->e;
            }
            return $this->response;
        }else{
            throw new \Exception("get response timeoout");
        }*/
        return $this->response;
    }
}