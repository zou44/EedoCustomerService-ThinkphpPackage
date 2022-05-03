<?php
/* =============================================================================#
# Description: 接收用户类型
#============================================================================= */

namespace SuperPig\EedoCustomerService\enum\chat_record;

class ToUserType
{
    const CLIENT  = 1;
    const SERVICE = 2;

    public static function getDescription($value)
    {
        switch ($value) {
            case self::CLIENT:
                return '用户';
            case self::SERVICE:
                return '服务';
            default:
                return '未知';
        }
    }
}