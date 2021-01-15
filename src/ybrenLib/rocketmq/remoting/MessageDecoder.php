<?php
namespace ybrenLib\rocketmq\remoting;
use ybrenLib\logger\LoggerFactory;
use ybrenLib\rocketmq\entity\MessageConst;
use ybrenLib\rocketmq\entity\MessageExt;
use ybrenLib\rocketmq\exception\RocketMQClientException;
use ybrenLib\rocketmq\MQConstants;

class MessageDecoder
{

    /**
     * @param $recvData
     * @return RemotingCommand
     * @throws RocketMQClientException
     */
    public static function decode($recvData){
        $remotingCommand = new RemotingCommand();
        $byteBuf = new ByteBuf($recvData);

        // 跳过前面的数据
        $byteBuf->index(4);

        $headLength = $byteBuf->readInt();
        $length = strlen($recvData);

        // 读取header
        $headerJson = $byteBuf->readString($headLength);
        $headerArray = json_decode($headerJson , true);
        if(empty($headerArray)){
            throw new RocketMQClientException("response data is error:" . $recvData);
        }
        $remotingCommand->setCode($headerArray['code']);
        $remotingCommand->setFlag($headerArray['flag'] ?? 1);
        if(isset($headerArray['extFields'])){
            $remotingCommand->setExtFields($headerArray['extFields']);
        }
        isset($headerArray['opaque']) && $remotingCommand->setOpaque($headerArray['opaque']);

        $bodyLength = $length - 4 - $headLength;
        if($bodyLength > 0){
            $body = $byteBuf->readString($bodyLength);
            $remotingCommand->setBody($body);
        }

        return $remotingCommand;
    }

    /**
     * @param ByteBuf $byteBuf
     * @param RemotingCommand $remotingCommand
     * @return MessageExt
     */
    public static function decodeMessage(ByteBuf $byteBuf, RemotingCommand $remotingCommand = null){
        $messageExt = new MessageExt();

        // 读取长度
        $storeSize = $byteBuf->readInt();
        $messageExt->setStoreSize($storeSize);

        // 忽略魔术常量
        $byteBuf->readInt();

        // BODYCRC
        $bodyCrc = $byteBuf->readInt();
        $messageExt->setBodyCRC($bodyCrc);

        // queueId
        $queueId = $byteBuf->readInt();
        $messageExt->setQueueId($queueId);

        // FLAG
        $flag = $byteBuf->readInt();
        $messageExt->setFlag($flag);

        // QUEUEOFFSET
        $queueOffset = $byteBuf->readLong();
        $messageExt->setQueueOffset($queueOffset);

        // physicOffset
        $physicOffset = $byteBuf->readLong();
       $messageExt->setCommitLogOffset($physicOffset);

        // sysFlag
        $sysFlag = $byteBuf->readInt();
        $messageExt->setSysFlag($sysFlag);

        // bornTimeStamp
        $bornTimeStamp = $byteBuf->readLong();
        $messageExt->setBornTimestamp($bornTimeStamp);

        // 忽略BORNHOST
        $bornHost = $byteBuf->readString(4);

        // 忽略BORNPORT
        $port = $byteBuf->readInt();

        // storeTimestamp
        $storeTimestamp = $byteBuf->readLong();
        $messageExt->setStoreTimestamp($storeTimestamp);

        // STOREHOST
        $storehostIPLength = 4;
        $storeHosts = $byteBuf->readBytes($storehostIPLength);

        // STOREPORT
        $storePort = $byteBuf->readInt();
       // $messageExt->setStoreHost($storeHost . $port);

        // RECONSUMETIMES
        $reconsumeTimes = $byteBuf->readInt();
        $messageExt->setReconsumeTimes($reconsumeTimes);

        // preparedTransactionOffset
        $preparedTransactionOffset = $byteBuf->readLong();
        $messageExt->setPreparedTransactionOffset($preparedTransactionOffset);

        // body
        $bodyLen = $byteBuf->readInt();
        if($bodyLen > 0){
            $body = $byteBuf->readString($bodyLen);
            $messageExt->setBody($body);
        }

        // propertiesLength
        $propertiesLength = $byteBuf->readShort();
        if($propertiesLength > 0){
            $properties = $byteBuf->readString($propertiesLength);
            $propertiesArray = MessageDecoder::string2messageProperties($properties);
            $messageExt->setProperties($propertiesArray);
        }

        // msgId
        $msgId = self::createMessageId($storeHosts , $storePort , $physicOffset);
        $messageExt->setMsgId($msgId);
        if($remotingCommand != null){
            $headerMap = $remotingCommand->getExtFields();
            isset($headerMap['offsetMsgId']) && $messageExt->setMsgId($headerMap['offsetMsgId']);
            isset($headerMap['commitLogOffset']) && $messageExt->setCommitLogOffset($headerMap['commitLogOffset']);
            isset($headerMap['tranStateTableOffset']) && $messageExt->setTranStateTableOffset($headerMap['tranStateTableOffset']);
            isset($headerMap['transactionId']) && $messageExt->setTransactionId($headerMap['transactionId']);
        }

        // topic
        $messageExt->setTopic($messageExt->getProperty(MessageConst::PROPERTY_REAL_TOPIC));

        return $messageExt;
    }

    private static function decodeIpHost($addr){
        $addr = (string)$addr;
        $address  = ord($addr[3]) & 0xFF;
        $address |= ((ord($addr[2]) << 8) & 0xFF00);
        $address |= ((ord($addr[1]) << 16) & 0xFF0000);
        $address |= ((ord($addr[0]) << 24) & 0xFF000000);
        return $address;
    }

    /**
     * @param ByteBuf $byteBuf
     * @param RemotingCommand $remotingCommand
     * @return MessageExt[]
     */
    public static function decodeMessages(ByteBuf $byteBuf , RemotingCommand $remotingCommand){
        $messageExt = [];
        while ($byteBuf->hasRemaining()){
            $messageExt[] = self::decodeMessage($byteBuf , $remotingCommand);
        }
        return $messageExt;
    }

    /**
     * @param $properties
     * @return string
     */
    public static function messageProperties2String($properties){
        $sb = "";
        if ($properties != null) {
            foreach ($properties as $name => $value) {
                if ($value == null) {
                    continue;
                }
                $sb .= $name . self::getSeparator(MQConstants::NAME_VALUE_SEPARATOR) . $value . self::getSeparator(MQConstants::PROPERTY_SEPARATOR);
            }
        }
        return $sb;
    }

    public static function string2messageProperties(string $properties) {
        $map = [];
        if ($properties != null) {
            $items = explode(self::getSeparator(MQConstants::PROPERTY_SEPARATOR) , $properties);
            foreach ($items as $i) {
                $nv = explode(self::getSeparator(MQConstants::NAME_VALUE_SEPARATOR) , $i);
                if (2 == count($nv)) {
                    $key = $nv[0];
                    if(strrpos($key , MessageConst::PROPERTY_KEYS) == (strlen($key) - strlen(MessageConst::PROPERTY_KEYS))){
                        $key = MessageConst::PROPERTY_KEYS;
                    }
                    $map[$key] = $nv[1];
                }
            }
        }

        return $map;
    }

    private static function getSeparator($sep){
        return chr($sep);
    }

    public static function decodeMessageId($msgId){
        $ipLength = strlen($msgId) == 32 ? 4 * 2 : 16 * 2;
        // offset
        $data = self::string2bytes(substr($msgId , $ipLength + 8, $ipLength + 8 + 16));
        $bb = new ByteBuf($data);
        return $bb->readLong();
    }

    private static function string2bytes($hexString) {
        if (empty($hexString)) {
            return null;
        }
        $byte = [];
        $hexString = strtoupper($hexString);
        $hexString = (string)$hexString;
        $length = strlen($hexString) / 2;
        for ($i = 0;$i < $length;$i++){
            $pos = $i * 2;
            $c1 = self::charToByte($hexString[$pos]);
            $c2 = self::charToByte($hexString[$pos + 1]);
            $c = chr($c1 << 4 | $c2);
            $byte[$i] = $c;
        }
        return $byte;
    }

    private static function charToByte($c) {
        return strpos("0123456789ABCDEF" , $c);
    }

    /**
     * 构建消息ID
     * @param $storeHosts
     * @param $port
     * @param $commitLogOffset
     * @return string
     */
    public static function createMessageId($storeHosts , $port , $commitLogOffset)
    {
        $msgIdBuf = new ByteBuf();
        $msgIdBuf->writeBytes($storeHosts);
        $msgIdBuf->writeInt($port);
        $msgIdBuf->writeLong($commitLogOffset);
        $src = $msgIdBuf->flush();
        $hex_array = "0123456789ABCDEF";
        $hexChars = "";
        for ($j = 0; $j < strlen($src); $j++) {
            $v = ord($src[$j]) & 0xFF;
            $hexChars[$j * 2] = $hex_array[self::uright($v, 4)];
            $hexChars[$j * 2 + 1] = $hex_array[$v & 0x0F];
        }
        return $hexChars;
    }

    public static function uright($a, $n)
    {
        $c = 2147483647>>($n-1);
        return $c&($a>>$n);
    }
}