<?php
namespace ybrenLib\rocketmq\core;

use ybrenLib\rocketmq\entity\MessageQueue;

class ObjectMap implements \Iterator,\ArrayAccess,\JsonSerializable
{
    protected $keys = [];
    protected $values = [];

    public function put($key , $value){
        $hashCode = $this->getKey($key);
        $this->keys[$hashCode] = $key;
        $this->values[$hashCode] = $value;
        return $value;
    }
    
    public function get($key){
        $hashCode = $this->getKey($key);
        return $this->values[$hashCode] ?? null;
    }
    
    public function remove($key){
        $hashCode = $this->getKey($key);
        $value = $this->values[$hashCode] ?? null;
        unset($this->values[$hashCode]);
        unset($this->keys[$hashCode]);
        return $value;
    }
    
    public function keys(){
        return $this->keys;
    }
    
    public function current()
    {
        return current($this->values);
    }
    
    public function containsKey(MessageQueue $key){
        $hashCode = $this->getKey($key);
        return isset($this->values[$hashCode]);
    }
    
    public function next()
    {
        return next($this->values);
    }
    
    public function key()
    {
        $key = key($this->values);
        return $this->keys[$key];
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->current() !== false;
    }

    public function rewind()
    {
        reset($this->values);
    }

    public function contains($key){
        $hashCode = $this->getKey($key);
        return isset($this->values[$hashCode]);
    }

    public function offsetExists($offset)
    {
        return $this->contains($offset);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value)
    {
        $this->put($offset , $value);
    }

    public function offsetUnset($offset)
    {
        $this->remove($offset);
    }

    public function jsonSerialize()
    {
        return array_values($this->values);
    }

    public function size(){
        return count($this->values);
    }

    protected function getKey($key){
        if(is_object($key)){
            if(method_exists($key , "hashCode")){
                return $key->hashCode();
            }else{
                return spl_object_hash($key);
            }
        }
        return $key;
    }
}