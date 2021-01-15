<?php
namespace ybrenLib\rocketmq\consumer\listener;

class ConsumeConcurrentlyStatus
{
    const CONSUME_SUCCESS = "CONSUME_SUCCESS";

    const RECONSUME_LATER = "RECONSUME_LATER";
}