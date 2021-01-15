<?php
namespace ybrenLib\rocketmq\entity;

class SendStatus
{
    const SEND_OK = "SEND_OK";
    const FLUSH_DISK_TIMEOUT = "FLUSH_DISK_TIMEOUT";
    const FLUSH_SLAVE_TIMEOUT = "FLUSH_SLAVE_TIMEOUT";
    const SLAVE_NOT_AVAILABLE = "SLAVE_NOT_AVAILABLE";
}