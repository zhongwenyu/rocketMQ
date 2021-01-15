<?php
namespace ybrenLib\rocketmq\consumer\store;

class ReadOffsetType
{
    /**
     * From memory
     */
    const READ_FROM_MEMORY = "READ_FROM_MEMORY";
    /**
     * From storage
     */
    const READ_FROM_STORE = "READ_FROM_STORE";
    /**
     * From memory,then from storage
     */
    const MEMORY_FIRST_THEN_STORE = "MEMORY_FIRST_THEN_STORE";
}