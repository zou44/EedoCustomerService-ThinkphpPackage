<?php
/* =============================================================================#
# Description: 消息发送事件
#============================================================================= */

namespace SuperPig\EedoCustomerService\events\customer_service;


use SuperPig\EedoCustomerService\events\Event;

class SendMessage implements Event
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
}