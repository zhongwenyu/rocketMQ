<?php
namespace ybrenLib\rocketmq\remoting;

class ByteBuf{

    private $data;
    private $index = 0;
    private $length = 0;

    public function __construct($data = null){
        if(!is_null($data)){
            $this->data = $data;
            $this->length = strlen($data);
        }
    }

    public function writeByte($val){
        $this->writeData($val);
    }

    public function readTinyint(){
        $val = ord($this->data[$this->index++]);
        return intval($val & 0xff);
    }

    public function writeTinyInt($val){
        $val = intval($val);
        $this->writeData(chr($val & 0xFF));
    }

    public function readInt(){
        $val = ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        return intval($val);
    }

    public function writeInt($val){
        $val = (int)$val;
        $this->writeData(chr($val >> 24 & 0xff));
        $this->writeData(chr($val >> 16 & 0xFF));
        $this->writeData(chr($val >> 8 & 0xFF));
        $this->writeData(chr($val & 0xFF));//掩码运算
    }

    public function readLong(){
        $val = ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        return intval($val);
    }

    public function writeLong($val){
        $val = (int)$val;
        $this->writeData(chr($val >> 56 & 0xff));
        $this->writeData(chr($val >> 48 & 0xff));
        $this->writeData(chr($val >> 40 & 0xff));
        $this->writeData(chr($val >> 32 & 0xff));
        $this->writeData(chr($val >> 24 & 0xff));
        $this->writeData(chr($val >> 16 & 0xFF));
        $this->writeData(chr($val >> 8 & 0xFF));
        $this->writeData(chr($val & 0xFF));//掩码运算
    }

    public function readShort(){
        $val = ord($this->data[$this->index++]) & 0xff;
        $val <<= 8;
        $val |= ord($this->data[$this->index++]) & 0xff;
        return intval($val);
    }

    public function writeShort($val){
        $val = intval($val);
        $this->writeData(chr($val >> 8 & 0xff));
        $this->writeData(chr($val & 0xff));
    }

    public function readString($length = null){
        $str = "";
        is_null($length) && $length = $this->readShort();
        for($i = 0;$i < $length;$i++){
            $str .= $this->data[$this->index++] ?? "";
        }
        return $str;
    }

    public function writeString($str , $writeLength = true){
        $str = (string) $str;
        $strLength = strlen($str);
        // 写入字符串长度
        $writeLength && $this->writeShort($strLength);
        for($i = 0; $i < $strLength; $i++) {
            $this->writeData($str[$i]);
        }
        // 返回字符串长度
        return $strLength + ($writeLength ? 2 : 0);
    }

    private function writeData($val){
        $this->data[$this->index] = $val;
        $this->index++;
    }

    public function index($index = null){
        if(!is_null($index)){
            $this->index = $index;
        }
        return $this->index;
    }

    public function readBytes($length){
        $data = [];
        for($i = 0;$i < $length;$i++){
            $data[] = $this->data[$this->index++];
        }
        return $data;
    }

    public function writeBytes($data){
        for($i = 0;$i < count($data);$i++){
            $this->data[$this->index++] = $data[$i];
        }
    }

    public function flush(){
        $buffer = str_repeat(chr(0), count($this->data));
        ksort($this->data);
        foreach ($this->data as $key => $item){
            $buffer[$key] = $item;
        }
        $this->data = null;
        $this->index = 0;
        return $buffer;
    }

    /**
     * @return bool
     */
    public function hasRemaining(){
        return $this->index < ($this->length - 1);
    }
}