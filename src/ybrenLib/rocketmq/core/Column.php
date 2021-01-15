<?php


namespace ybrenLib\rocketmq\core;


abstract class Column implements \ArrayAccess,\JsonSerializable
{
    public function __construct($data = []){
        if(!empty($data)){
            foreach ($data as $key => $val){
                if(property_exists($this , $key) && !is_null($val)){
                    $setMethodName = $this->getSetMethodName($key);
                    if(method_exists($this , $setMethodName)){
                        $this->$setMethodName($val);
                    }else{
                        $this->$key = $val;
                    }
                }
            }
        }
    }

    /**
     * 属性是否存在
     * @param $key
     * @return bool
     */
    public function offsetExists($key){
        if(!property_exists($this , $key)){
            return false;
        }elseif(is_null($this->$key)){
            return false;
        }
        return true;
    }

    /**
     * 设置属性值
     * @param $key
     * @param $value
     */
    public function offsetSet($key, $value){
        if(!is_null($value) && property_exists($this , $key)){
            $setMethodName = $this->getSetMethodName($key);
            if(method_exists($this , $setMethodName)){
                $this->$setMethodName($value);
            }else{
                $this->$key = $value;
            }
        }
    }

    /**
     * 获取属性值
     * @param $key
     * @return mixed
     */
    public function offsetGet($key){
        if(!property_exists($this , $key)){
            return null;
        }
        return $this->$key;
    }

    /**
     * 属性值初始化
     * @param $key
     */
    public function offsetUnset($key){
        if(property_exists($this , $key)){
            $val = $this->$key;
            if(is_float($val)){
                $this->$key = 0.00;
            }elseif(is_int($val)){
                $this->$key = 0;
            }elseif(is_string($val)){
                $this->$key = "";
            }elseif(is_object($val) && $val instanceof self){
                $reflectClass = new \ReflectionClass($val);
                $className = $reflectClass->getName();
                $this->$key = new $className();
            }else{
                $this->$key = [];
            }
        }
    }

    /**
     * 判断是否为空
     */
    public function isEmpty(){
        return false;
    }

    public function __set($name, $value){
        if(property_exists($this , $name) && !is_null($value)){
            $setMethodName = $this->getSetMethodName($name);
            if(method_exists($this , $setMethodName)){
                $this->$setMethodName($value);
            }else{
                $this->$name = $value;
            }
        }
    }

    public function __get($name){
        if(property_exists($this , $name)){
            $getMethodName = $this->getGetMethodName($name);
            return method_exists($this , $getMethodName) ? $this->$getMethodName() : $this->$name;
        }else{
            return null;
        }
    }

    public function toArray(){
        $data = [];
        foreach ($this as $key=>$val){
            $getMethodName = $this->getGetMethodName($key);
            if ($val !== null) $data[$key] = method_exists($this , $getMethodName) ? $this->$getMethodName() : $this->$key;
        }
        return $data;
    }

    public function jsonSerialize() {
        return $this->toArray();
    }

    /**
     * 获取set方法名称
     * @param $key
     * @return string
     */
    protected function getSetMethodName($key){
        return "set".ucfirst($key);
    }

    /**
     * 获取get方法名称
     * @param $key
     * @return string
     */
    protected function getGetMethodName($key){
        return "get".ucfirst($key);
    }

    public function getUniqueId(){
        return md5(json_encode($this));
    }
}