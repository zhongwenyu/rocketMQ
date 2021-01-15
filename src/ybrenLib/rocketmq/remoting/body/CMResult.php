<?php
namespace ybrenLib\rocketmq\remoting\body;

class CMResult
{
    const CR_SUCCESS = "CR_SUCCESS";
    const CR_LATER = "CR_LATER";
    const CR_ROLLBACK = "CR_ROLLBACK";
    const CR_COMMIT = "CR_COMMIT";
    const CR_THROW_EXCEPTION = "CR_THROW_EXCEPTION";
    const CR_RETURN_NULL = "CR_RETURN_NULL";
}