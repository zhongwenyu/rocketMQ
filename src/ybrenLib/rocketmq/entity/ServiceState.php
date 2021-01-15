<?php
namespace ybrenLib\rocketmq\entity;

class ServiceState
{
    const CREATE_JUST = "CREATE_JUST";

    const RUNNING = "RUNNING";

    const SHUTDOWN_ALREADY = "SHUTDOWN_ALREADY";

    const START_FAILED = "START_FAILED";
}