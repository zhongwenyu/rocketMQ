# rocketmq-php-client

rocketmq客户端-php

producer

# 普通消息
$defaultMQProducer = new DefaultMQProducer("producerGroupUser");  
$defaultMQProducer->setNamesrvAddr("192.168.8.240:9876");  
$defaultMQProducer->start();  
$message = new Message("topic_test" , "测试消息");  
// 设置消费唯一键  
$message->setKeys("FD816678378ABA7080BD3D7B39D4D29D");  
try{  
// 发送普通消息  
$sendResult = $defaultMQProducer->send($message);  
} finally {  
$defaultMQProducer->shutdown();  
}

# 延时消息
$defaultMQProducer = new DefaultMQProducer("producerGroupUser");  
$defaultMQProducer->setNamesrvAddr("192.168.8.240:9876");  
$defaultMQProducer->start();  
$message = new Message("topic_test" , "测试消息");  
// 设置消费唯一键  
$message->setKeys("FD816678378ABA7080BD3D7B39D4D29D");  
// 设置延时级别  
$message->setDelayTimeLevel(DelayLevel::Level_1m);  
try{  
// 发送延时消息  
$sendResult = $defaultMQProducer->send($message);  
} finally {  
$defaultMQProducer->shutdown();  
}

# 顺序消息
$defaultMQProducer = new DefaultMQProducer("producerGroupUser");  
$defaultMQProducer->setNamesrvAddr("192.168.8.240:9876");  
$defaultMQProducer->start();  
$message = new Message("topic_test" , "测试消息");  
// 设置消费唯一键  
$message->setKeys("FD816678378ABA7080BD3D7B39D4D29D");  
try{  
// 发送顺序消息  
$userId = "12910737";   
$sendResult = $defaultMQProducer->send($message , function ($messageQueueList , $message) use ($userId){
// 指定队列  
return $userId % count($messageQueueList);  
});  
} finally {
$defaultMQProducer->shutdown();  
}

# 事务消息
$transactionMQProducer = new TransactionMQProducer("producerGroupUser");  
$transactionMQProducer->setNamesrvAddr("192.168.8.240:9876");  
$transactionMQProducer->start();  
$message = new Message("topic_test" , "测试消息");  
// 设置消费唯一键  
$message->setKeys("FD816678378ABA7080BD3D7B39D4D29D");  
// 设置事务回查  
$transactionMQProducer->setTransactionListener(new TransactionCheckRollback());  
try{  
// 发送事务消息  
$sendResult = $transactionMQProducer->sendMessageInTransaction($message);  
// 提交事务消息  
$rp = $transactionMQProducer->rollback($sendResult);  
} finally {  
$transactionMQProducer->shutdown();  
}

comsumer  
1.定义消费处理对象（顺序消息实现接口MessageListenerOrderly，其他消息实现接口MessageListenerConcurrently）  
注意：  
（1）$msg中的topic不一定是所消费的topic，也有可能是重试的topic，若需要获取topic，最好是在new MessageListener的时候传进去  
（2）$msg中的msgId不可作为消息唯一标识，应使用getKeys()  
class MessageListener implements MessageListenerConcurrently  
{  
/**  
* @param MessageExt[] $msgs  
* @param ConsumeConcurrentlyContext $context  
* @return string  
*/  
function consumeMessage(array $msgs, ConsumeConcurrentlyContext $context)  
{  
foreach ($msgs as $msg){  
var_dump(date("H:i:s.").substr(intval(microtime(true)*1000) , 10) . " handle message : " . $msg->getKeys() . " " . json_encode($msg->getProperties()) . " ". $msg->getBody());  
}  
return ConsumeConcurrentlyStatus::RECONSUME_LATER;  
}  
  
}  

2.swoole worker启动时添加消费启动  
$defaultMQConsumer = new DefaultMQConsumer("test_test");  
$defaultMQConsumer->setNamesrvAddr("192.168.0.106:9876");  
$defaultMQConsumer->subscribe("topic_test" , "*");  
$defaultMQConsumer->setMessageListener(new MessageListener());  
$defaultMQConsumer->setConsumeFromWhere(ConsumeFromWhere::CONSUME_FROM_FIRST_OFFSET);  
$defaultMQConsumer->start();  

