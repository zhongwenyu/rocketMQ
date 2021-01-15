<?php
namespace ybrenLib\rocketmq\util;

use ybrenLib\rocketmq\core\Column;

class ArrayUtil
{

    /**
     * @param $item
     * @param array $array
     * @return bool
     */
    public static function inArray($item , array $array){
        if(is_object($item)){
            $hashCode = method_exists($item , "hashCode") ? $item->hashCode() : spl_object_hash($item);
            if(!empty($array)){
                foreach ($array as $value){
                    if(!is_null($value) && is_object($value)){
                        $compareHashCode = method_exists($value , "hashCode") ? $value->hashCode() : spl_object_hash($value);
                        if($compareHashCode == $hashCode){
                            return true;
                        }
                    }
                }
            }
        }
        return in_array($item , $array);
    }
}