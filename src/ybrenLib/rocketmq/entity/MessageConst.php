<?php


namespace ybrenLib\rocketmq\entity;


class MessageConst
{
    const PROPERTY_KEYS = "KEYS";
    const PROPERTY_TAGS = "TAGS";
    const PROPERTY_WAIT_STORE_MSG_OK = "WAIT";
    const PROPERTY_DELAY_TIME_LEVEL = "DELAY";
    const PROPERTY_RETRY_TOPIC = "RETRY_TOPIC";
    const PROPERTY_REAL_TOPIC = "REAL_TOPIC";
    const PROPERTY_REAL_QUEUE_ID = "REAL_QID";
    const PROPERTY_TRANSACTION_PREPARED = "TRAN_MSG";
    const PROPERTY_PRODUCER_GROUP = "PGROUP";
    const PROPERTY_MIN_OFFSET = "MIN_OFFSET";
    const PROPERTY_MAX_OFFSET = "MAX_OFFSET";
    const PROPERTY_BUYER_ID = "BUYER_ID";
    const PROPERTY_ORIGIN_MESSAGE_ID = "ORIGIN_MESSAGE_ID";
    const PROPERTY_TRANSFER_FLAG = "TRANSFER_FLAG";
    const PROPERTY_CORRECTION_FLAG = "CORRECTION_FLAG";
    const PROPERTY_MQ2_FLAG = "MQ2_FLAG";
    const PROPERTY_RECONSUME_TIME = "RECONSUME_TIME";
    const PROPERTY_MSG_REGION = "MSG_REGION";
    const PROPERTY_TRACE_SWITCH = "TRACE_ON";
    const PROPERTY_UNIQ_CLIENT_MESSAGE_ID_KEYIDX = "UNIQ_KEY";
    const PROPERTY_MAX_RECONSUME_TIMES = "MAX_RECONSUME_TIMES";
    const PROPERTY_CONSUME_START_TIMESTAMP = "CONSUME_START_TIME";
    const PROPERTY_TRANSACTION_PREPARED_QUEUE_OFFSET = "TRAN_PREPARED_QUEUE_OFFSET";
    const PROPERTY_TRANSACTION_CHECK_TIMES = "TRANSACTION_CHECK_TIMES";
    const PROPERTY_CHECK_IMMUNITY_TIME_IN_SECONDS = "CHECK_IMMUNITY_TIME_IN_SECONDS";
    const PROPERTY_INSTANCE_ID = "INSTANCE_ID";
    const PROPERTY_CORRELATION_ID = "CORRELATION_ID";
    const PROPERTY_MESSAGE_REPLY_TO_CLIENT = "REPLY_TO_CLIENT";
    const PROPERTY_MESSAGE_TTL = "TTL";
    const PROPERTY_REPLY_MESSAGE_ARRIVE_TIME = "ARRIVE_TIME";
    const PROPERTY_PUSH_REPLY_TIME = "PUSH_REPLY_TIME";
    const PROPERTY_CLUSTER = "CLUSTER";
    const PROPERTY_MESSAGE_TYPE = "MSG_TYPE";

    const KEY_SEPARATOR = " ";
    
    public static $stringHashSet = [
        MessageConst::PROPERTY_TRACE_SWITCH,
        MessageConst::PROPERTY_MSG_REGION,
        MessageConst::PROPERTY_KEYS,
        MessageConst::PROPERTY_TAGS,
        MessageConst::PROPERTY_WAIT_STORE_MSG_OK,
        MessageConst::PROPERTY_DELAY_TIME_LEVEL,
        MessageConst::PROPERTY_RETRY_TOPIC,
        MessageConst::PROPERTY_REAL_TOPIC,
        MessageConst::PROPERTY_REAL_QUEUE_ID,
        MessageConst::PROPERTY_TRANSACTION_PREPARED,
        MessageConst::PROPERTY_PRODUCER_GROUP,
        MessageConst::PROPERTY_MIN_OFFSET,
        MessageConst::PROPERTY_MAX_OFFSET,
        MessageConst::PROPERTY_BUYER_ID,
        MessageConst::PROPERTY_ORIGIN_MESSAGE_ID,
        MessageConst::PROPERTY_TRANSFER_FLAG,
        MessageConst::PROPERTY_CORRECTION_FLAG,
        MessageConst::PROPERTY_MQ2_FLAG,
        MessageConst::PROPERTY_RECONSUME_TIME,
        MessageConst::PROPERTY_UNIQ_CLIENT_MESSAGE_ID_KEYIDX,
        MessageConst::PROPERTY_MAX_RECONSUME_TIMES,
        MessageConst::PROPERTY_CONSUME_START_TIMESTAMP,
        MessageConst::PROPERTY_INSTANCE_ID,
        MessageConst::PROPERTY_CORRELATION_ID,
        MessageConst::PROPERTY_MESSAGE_REPLY_TO_CLIENT,
        MessageConst::PROPERTY_MESSAGE_TTL,
        MessageConst::PROPERTY_REPLY_MESSAGE_ARRIVE_TIME,
        MessageConst::PROPERTY_PUSH_REPLY_TIME,
        MessageConst::PROPERTY_CLUSTER,
        MessageConst::PROPERTY_MESSAGE_TYPE
    ];
}