<?php
/* =============================================================================#
# Description: 登陆事件
#============================================================================= */

namespace SuperPig\EedoCustomerService\events\customer_service;


use SuperPig\EedoCustomerService\events\Event;

class Login implements Event
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
}