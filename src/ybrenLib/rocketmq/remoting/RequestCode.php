<?php
namespace ybrenLib\rocketmq\remoting;

class RequestCode
{
    public static $SEND_MESSAGE = 10;

    public static $PULL_MESSAGE = 11;

    public static $QUERY_MESSAGE = 12;
    public static $QUERY_BROKER_OFFSET = 13;
    public static $QUERY_CONSUMER_OFFSET = 14;
    public static $UPDATE_CONSUMER_OFFSET = 15;
    public static $UPDATE_AND_CREATE_TOPIC = 17;
    public static $GET_ALL_TOPIC_CONFIG = 21;
    public static $GET_TOPIC_CONFIG_LIST = 22;

    public static $GET_TOPIC_NAME_LIST = 23;

    public static $UPDATE_BROKER_CONFIG = 25;

    public static $GET_BROKER_CONFIG = 26;

    public static $TRIGGER_DELETE_FILES = 27;

    public static $GET_BROKER_RUNTIME_INFO = 28;
    public static $SEARCH_OFFSET_BY_TIMESTAMP = 29;
    public static $GET_MAX_OFFSET = 30;
    public static $GET_MIN_OFFSET = 31;

    public static $GET_EARLIEST_MSG_STORETIME = 32;

    public static $VIEW_MESSAGE_BY_ID = 33;

    public static $HEART_BEAT = 34;

    public static $UNREGISTER_CLIENT = 35;

    public static $CONSUMER_SEND_MSG_BACK = 36;

    public static $END_TRANSACTION = 37;
    public static $GET_CONSUMER_LIST_BY_GROUP = 38;

    public static $CHECK_TRANSACTION_STATE = 39;

    public static $NOTIFY_CONSUMER_IDS_CHANGED = 40;

    public static $LOCK_BATCH_MQ = 41;

    public static $UNLOCK_BATCH_MQ = 42;
    public static $GET_ALL_CONSUMER_OFFSET = 43;

    public static $GET_ALL_DELAY_OFFSET = 45;

    public static $CHECK_CLIENT_CONFIG = 46;

    public static $UPDATE_AND_CREATE_ACL_CONFIG = 50;

    public static $DELETE_ACL_CONFIG = 51;

    public static $GET_BROKER_CLUSTER_ACL_INFO = 52;

    public static $UPDATE_GLOBAL_WHITE_ADDRS_CONFIG = 53;

    public static $GET_BROKER_CLUSTER_ACL_CONFIG = 54;

    public static $PUT_KV_CONFIG = 100;

    public static $GET_KV_CONFIG = 101;

    public static $DELETE_KV_CONFIG = 102;

    public static $REGISTER_BROKER = 103;

    public static $UNREGISTER_BROKER = 104;
    public static $GET_ROUTEINFO_BY_TOPIC = 105;

    public static $GET_BROKER_CLUSTER_INFO = 106;
    public static $UPDATE_AND_CREATE_SUBSCRIPTIONGROUP = 200;
    public static $GET_ALL_SUBSCRIPTIONGROUP_CONFIG = 201;
    public static $GET_TOPIC_STATS_INFO = 202;
    public static $GET_CONSUMER_CONNECTION_LIST = 203;
    public static $GET_PRODUCER_CONNECTION_LIST = 204;
    public static $WIPE_WRITE_PERM_OF_BROKER = 205;

    public static $GET_ALL_TOPIC_LIST_FROM_NAMESERVER = 206;

    public static $DELETE_SUBSCRIPTIONGROUP = 207;
    public static $GET_CONSUME_STATS = 208;

    public static $SUSPEND_CONSUMER = 209;

    public static $RESUME_CONSUMER = 210;
    public static $RESET_CONSUMER_OFFSET_IN_CONSUMER = 211;
    public static $RESET_CONSUMER_OFFSET_IN_BROKER = 212;

    public static $ADJUST_CONSUMER_THREAD_POOL = 213;

    public static $WHO_CONSUME_THE_MESSAGE = 214;

    public static $DELETE_TOPIC_IN_BROKER = 215;

    public static $DELETE_TOPIC_IN_NAMESRV = 216;
    public static $GET_KVLIST_BY_NAMESPACE = 219;

    public static $RESET_CONSUMER_CLIENT_OFFSET = 220;

    public static $GET_CONSUMER_STATUS_FROM_CLIENT = 221;

    public static $INVOKE_BROKER_TO_RESET_OFFSET = 222;

    public static $INVOKE_BROKER_TO_GET_CONSUMER_STATUS = 223;

    public static $QUERY_TOPIC_CONSUME_BY_WHO = 300;

    public static $GET_TOPICS_BY_CLUSTER = 224;

    public static $REGISTER_FILTER_SERVER = 301;
    public static $REGISTER_MESSAGE_FILTER_CLASS = 302;

    public static $QUERY_CONSUME_TIME_SPAN = 303;

    public static $GET_SYSTEM_TOPIC_LIST_FROM_NS = 304;
    public static $GET_SYSTEM_TOPIC_LIST_FROM_BROKER = 305;

    public static $CLEAN_EXPIRED_CONSUMEQUEUE = 306;

    public static $GET_CONSUMER_RUNNING_INFO = 307;

    public static $QUERY_CORRECTION_OFFSET = 308;
    public static $CONSUME_MESSAGE_DIRECTLY = 309;

    public static $SEND_MESSAGE_V2 = 310;

    public static $GET_UNIT_TOPIC_LIST = 311;

    public static $GET_HAS_UNIT_SUB_TOPIC_LIST = 312;

    public static $GET_HAS_UNIT_SUB_UNUNIT_TOPIC_LIST = 313;

    public static $CLONE_GROUP_OFFSET = 314;

    public static $VIEW_BROKER_STATS_DATA = 315;

    public static $CLEAN_UNUSED_TOPIC = 316;

    public static $GET_BROKER_CONSUME_STATS = 317;

    /**
     * update the config of name server
     */
    public static $UPDATE_NAMESRV_CONFIG = 318;

    /**
     * get config from name server
     */
    public static $GET_NAMESRV_CONFIG = 319;

    public static $SEND_BATCH_MESSAGE = 320;

    public static $QUERY_CONSUME_QUEUE = 321;

    public static $QUERY_DATA_VERSION = 322;

    /**
     * resume logic of checking half messages that have been put in TRANS_CHECK_MAXTIME_TOPIC before
     */
    public static $RESUME_CHECK_HALF_MESSAGE = 323;

    public static $SEND_REPLY_MESSAGE = 324;

    public static $SEND_REPLY_MESSAGE_V2 = 325;

    public static $PUSH_REPLY_MESSAGE_TO_CLIENT = 326;


}