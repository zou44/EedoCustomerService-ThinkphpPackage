<?php
/* =============================================================================#
# Description:
#============================================================================= */

namespace SuperPig\EedoCustomerService;

use Exception;
use GatewayWorker\BusinessWorker;
use GatewayWorker\Lib\Gateway;
use ReflectionClass;
use ReflectionMethod;
use SuperPig\EedoCustomerService\exception\AuthenticationException;
use SuperPig\EedoCustomerService\exception\InvalidRequestException;
use SuperPig\EedoCustomerService\logic\Admin;
use SuperPig\EedoCustomerService\logic\Client;
use SuperPig\EedoCustomerService\logic\CustomerService;
use think\facade\Config;
use think\facade\Log;
use think\helper\Str;
use Workerman\MySQL\Connection;
use SuperPig\EedoCustomerService\enum;

class Events
{
    // 逻辑配置
    public static $logicConfig = [];
    // 逻辑事件映射
    public static $logicEventMap = [];

    // 变量共享组件
    public static $globalData;
    // mysql配置信息
    public static $mysqlConfig;
    // mysql实例
    public static $mysqlDb;

    public static function onWorkerStart(BusinessWorker $businessWorker)
    {
        // 加载逻辑配置
        self::$logicConfig = Config::get('eedo.logic');
        // 增加事件映射
        foreach ([Client::class, CustomerService::class, Admin::class] as $cls) {
            $reflClass = new ReflectionClass($cls);
            $key       = Str::snake($reflClass->getShortName());
            if (!isset(self::$logicEventMap[$key])) self::$logicEventMap[$key] = ['methods' => [], 'namespace' => $cls];
            foreach ($reflClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                array_push(self::$logicEventMap[$key]['methods'], Str::snake($method->name));
            }
            unset($reflClass);
        }

        // 建立进程变量共享组件连接
        $globalDataAddress = Config::get('eedo.server.global_data');
        self::$globalData = new \GlobalData\Client($globalDataAddress['ip'].':'.$globalDataAddress['port']);
        // 初始化 客服在线列表
        self::$globalData->add('online_customer_services', array(
            //'customer_service_id' => array(
            //    // 接待列表
            //    'rcpt_lists' => [
            //        'uuid' => []
            //    ],
            //)
        ));
        // 初始化 用户UUID到客服(正在聊天中的...)
        self::$globalData->add('uuid_to_customer_services', array(
            //'uuid' => []
        ));

        // TODO:后期需要扩展更多的存储方式
        // 建立mysql连接
        self::$mysqlConfig = Config::get('eedo.data.mysql');
        self::$mysqlDb = new Connection(
            self::$mysqlConfig['host'],
            self::$mysqlConfig['port'],
            self::$mysqlConfig['user'],
            self::$mysqlConfig['pass'],
            self::$mysqlConfig['name'],
            self::$mysqlConfig['charset']
        );
    }

    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect2a
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {

    }

    /**
     * 当客户端发来消息时触发
     *
     * @param $clientId
     * @param $messageData
     * @throws \SuperPig\EedoCustomerService\exception\InvalidRequestException
     */
    public static function onMessage($clientId, $messageData)
    {
        $messageDataJson = json_decode($messageData, true);
        if (!isset($messageDataJson['c'])) throw new InvalidRequestException('missing parameters');
        $method = $messageDataJson['c'];
        $data   = isset($messageDataJson['d']) && is_array($messageDataJson['d']) ? $messageDataJson['d'] : [];

        try {
            // 鉴权
            if ($method === 'auth') {
                // 参数合法性
                if (!isset($data['role'])) throw new InvalidRequestException('role parameter required');
                if (!in_array($data['role'], array_keys(self::$logicEventMap))) throw new InvalidRequestException('role does not exist');
                $role = $data['role'];

                // 禁止重复验证
                if (isset($_SESSION['role'])) throw new InvalidRequestException('repeat authentication');
                $uuid = forward_static_call_array([
                    self::$logicEventMap[$role]['namespace'],
                    '__authentication',
                ], [$clientId, $data, []]);
                //if (!is_string($uuid)) throw new AuthenticationException('uuid generation error');

                // 用户赋值
                $_SESSION['role'] = $role;
                $_SESSION['uuid'] = $uuid;
                $_SESSION['client_id'] = $clientId;
                Gateway::bindUid($clientId, $uuid);
                // 发送通知
                Gateway::sendToCurrentClient(Helper::formatResponseData('authentication success', [], 'auth',200));

                // 相应事件
                forward_static_call_array(array(
                    self::$logicEventMap[$role]['namespace'],
                    '__eventAfterAuthentication'
                ), array($data));
            } else {
                $role = $_SESSION['role'];
                if (empty($role)) throw new AuthenticationException('not authenticated');

                $methodIndex = array_search($method, self::$logicEventMap[$role]['methods']);
                if ($methodIndex === false) throw new InvalidRequestException('method does not exist');
                $methodName = Str::camel(self::$logicEventMap[$role]['methods'][$methodIndex]);
                forward_static_call_array(array(self::$logicEventMap[$role]['namespace'], $methodName), array($data));
            }
        } catch (AuthenticationException $e) {
            // 验证失败主动断开
            Gateway::sendToCurrentClient(Helper::formatResponseData('active close', [], $method, $e->getCode()));
            Gateway::closeCurrentClient();
        } catch (InvalidRequestException $e) {
            Gateway::sendToCurrentClient(Helper::formatResponseData($e->getMessage(), [], $method, $e->getCode()));
        } catch(Exception $e) {
            Log::log('eedo_error', (string)$e);
        }
    }

    /**
     * 当用户断开连接时触发
     *
     * @param $clientId
     */
    public static function onClose($clientId)
    {
        if(isset($_SESSION['role'])) {
            $role = $_SESSION['role'];
            forward_static_call_array(array(
                self::$logicEventMap[$role]['namespace'],
                '__onClose',
            ), array());
        }
    }

}