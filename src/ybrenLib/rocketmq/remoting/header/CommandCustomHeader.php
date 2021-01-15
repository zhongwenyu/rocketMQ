<?php
namespace ybrenLib\rocketmq\remoting\header;

interface CommandCustomHeader
{
    /**
     * @return array
     */
    function getHeader();
}