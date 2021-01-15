<?php
namespace ybrenLib\rocketmq\remoting;

class MessageEncoder{

    /**
     * @param RemotingCommand $remotingCommand
     * @return ByteBuf
     */
    public static function encode(RemotingCommand $remotingCommand){
        $byteBuf = new ByteBuf();
        $header = $remotingCommand->toArray();
        $body = $remotingCommand->getBody();
        $bodyLength = empty($body) ? 0 : strlen($body);

        // 处理头数据
        self::writeHeader($byteBuf , $header , $bodyLength);

        // 处理body
        if($bodyLength > 0){
            $byteBuf->writeString($body , false);
        }
        return $byteBuf;
    }

    private static function writeHeader(ByteBuf &$byteBuf , $header , $bodyLength){
        $headerJson = json_encode($header);
        $headerLength = strlen($headerJson);

        $length = 4;
        $length += $headerLength;
        $length += $bodyLength;

        // 写入总长度
        $byteBuf->writeInt($length);
        // 写入头长度
        $byteBuf->writeInt($headerLength);
        // 写入头数据
        $byteBuf->writeString($headerJson , false);
    }
}