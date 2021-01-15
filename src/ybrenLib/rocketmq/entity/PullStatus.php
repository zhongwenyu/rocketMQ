<?php
namespace ybrenLib\rocketmq\entity;

class PullStatus
{
    const FOUND = "FOUND";

    const NO_NEW_MSG = "NO_NEW_MSG";

    const NO_MATCHED_MSG = "NO_MATCHED_MSG";

    const OFFSET_ILLEGAL = "OFFSET_ILLEGAL";
}