<?php
/* =============================================================================#
# Description: 客服服务帐号状态
#============================================================================= */

namespace SuperPig\EedoCustomerService\enum\reception_record;

class State
{
    const ACCEPT = 1;
    const REMOVE = 2;

    public static function getDescription($value)
    {
        switch ($value) {
            case self::ACCEPT:
                return '接收';
            case self::REMOVE:
                return '移除';
            default:
                return '未知';
        }
    }
}