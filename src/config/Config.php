<?php
/* =============================================================================#
# Description: 配置
#============================================================================= */

use SuperPig\EedoCustomerService\logic\Admin;
use SuperPig\EedoCustomerService\logic\Client;
use SuperPig\EedoCustomerService\logic\CustomerService;
use SuperPig\EedoCustomerService\events;

return [
    // 服务配置
    'server' => [
        // 注册服务
        'register'        => [
            'ip'   => '127.0.0.1',
            'port' => 1238,
        ],
        // 网关服务
        'gateway'         => [
            'ip'     => '0.0.0.0',
            'port'   => '8282',
            'config' => [
                // 心跳
                'ping_interval'           => 30,
                // ping_interval内最少发一个请求
                'ping_not_response_limit' => 1,
                // 进程数
                'count'                   => 4,
                // 开始端口
                'start_port'              => 9000,
            ],
        ],
        // 业务服务
        'business_worker' => [
            'count'         => 4,
            'event_handler' => SuperPig\EedoCustomerService\Events::class,
        ],
        // 变量共享组件配置
        'global_data' => [
            'ip'     => '0.0.0.0',
            'port'   => '2207',
        ]
    ],

    // 数据
    'data' => [
        // 驱动
        'drive' => 'mysql',
        // 配置
        'mysql' => [
            // 数据库地址
            'host' => env('EEDO_DATA_MYSQL_HOST', '127.0.0.1'),
            // 端口
            'port' => env('EEDO_DATA_MYSQL_PORT', '3306'),
            // 账号
            'user' => env('EEDO_DATA_MYSQL_USER', ''),
            // 密码
            'pass' => env('EEDO_DATA_MYSQL_PASS', ''),
            // 数据库名
            'name' => env('EEDO_DATA_MYSQL_NAME', ''),
            // 编码
            'charset' => env('EEDO_DATA_MYSQL_CHARSET', 'utf8mb4'),
            // 前缀
            'table_prefix' => env('EEDO_DATA_MYSQL_TABLE_PREFIX', 'eedo_')
        ],
    ],

    // 逻辑配置
    'logic'  => [
        'client'           => [
            // 验证方式
            'authentication' => null,
            // 处理逻辑
            'handler'        => Client::class,
            // 事件监听
            'listen' => [
                // 消息发送事件
                events\client\SendMessage::class => [],
                // 登陆事件
                events\client\Login::class => [],
                // 登陆事件
                events\client\Logout::class => [],
            ],
        ],
        'customer_service' => [
            // 处理逻辑
            'handler' => CustomerService::class,
            // 事件监听
            'listen' => [
                // 消息发送事件
                events\customer_service\SendMessage::class => [],
                // 登陆事件
                events\customer_service\Login::class => [],
                // 登陆事件
                events\customer_service\Logout::class => [],
            ],
        ],
        'admin'            => [
            'handler' => Admin::class,
        ],
    ],

];