<?php
/* =============================================================================#
# Description: 客服服务帐号状态
#============================================================================= */
namespace SuperPig\EedoCustomerService\enum\customer_service_account;

class State
{
    const NORMAL = 1;
    const BAN    = 2;

    public static function getDescription($value)
    {
        switch ($value) {
            case self::NORMAL:
                return '默认';
            case self::BAN:
                return '禁止';
            default:
                return '未知';
        }
    }
}