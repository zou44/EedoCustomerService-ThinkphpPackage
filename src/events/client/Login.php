<?php
/* =============================================================================#
# Description: 登陆事件
#============================================================================= */

namespace SuperPig\EedoCustomerService\events\client;


use SuperPig\EedoCustomerService\events\Event;

class Login implements Event
{
    public $data;

    public function __construct($data)
    {
        $this->data = $data;
    }
}