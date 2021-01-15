<?php
namespace ybrenLib\rocketmq\remoting\heartbeat;

use ybrenLib\rocketmq\core\Column;

class ProducerData extends Column
{
    protected $groupName;

    /**
     * @return mixed
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @param mixed $groupName
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;
    }
}