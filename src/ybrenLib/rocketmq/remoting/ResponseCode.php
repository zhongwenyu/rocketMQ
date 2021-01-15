<?php
namespace ybrenLib\rocketmq\remoting;


class ResponseCode
{
    public static $SUCCESS = 0;

    public static $FLUSH_DISK_TIMEOUT = 10;

    public static $SLAVE_NOT_AVAILABLE = 11;

    public static $FLUSH_SLAVE_TIMEOUT = 12;

    public static $MESSAGE_ILLEGAL = 13;

    public static $SERVICE_NOT_AVAILABLE = 14;

    public static $VERSION_NOT_SUPPORTED = 15;

    public static $NO_PERMISSION = 16;

    public static $TOPIC_NOT_EXIST = 17;
    public static $TOPIC_EXIST_ALREADY = 18;
    public static $PULL_NOT_FOUND = 19;

    public static $PULL_RETRY_IMMEDIATELY = 20;

    public static $PULL_OFFSET_MOVED = 21;

    public static $QUERY_NOT_FOUND = 22;

    public static $SUBSCRIPTION_PARSE_FAILED = 23;

    public static $SUBSCRIPTION_NOT_EXIST = 24;

    public static $SUBSCRIPTION_NOT_LATEST = 25;

    public static $SUBSCRIPTION_GROUP_NOT_EXIST = 26;

    public static $FILTER_DATA_NOT_EXIST = 27;

    public static $FILTER_DATA_NOT_LATEST = 28;

    public static $TRANSACTION_SHOULD_COMMIT = 200;

    public static $TRANSACTION_SHOULD_ROLLBACK = 201;

    public static $TRANSACTION_STATE_UNKNOW = 202;

    public static $TRANSACTION_STATE_GROUP_WRONG = 203;
    public static $NO_BUYER_ID = 204;

    public static $NOT_IN_CURRENT_UNIT = 205;

    public static $CONSUMER_NOT_ONLINE = 206;

    public static $CONSUME_MSG_TIMEOUT = 207;

    public static $NO_MESSAGE = 208;

    public static $UPDATE_AND_CREATE_ACL_CONFIG_FAILED = 209;

    public static $DELETE_ACL_CONFIG_FAILED = 210;

    public static $UPDATE_GLOBAL_WHITE_ADDRS_CONFIG_FAILED = 211;
}