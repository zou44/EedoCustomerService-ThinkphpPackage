<?php
/* =============================================================================#
# Description: 客户端逻辑
#============================================================================= */

namespace SuperPig\EedoCustomerService\logic;


use GatewayWorker\Lib\Gateway;
use SuperPig\EedoCustomerService\Events;
use SuperPig\EedoCustomerService\exception\InvalidRequestException;
use SuperPig\EedoCustomerService\exception\LogicException;
use SuperPig\EedoCustomerService\Helper;
use Exception;
use think\facade\Config;
use SuperPig\EedoCustomerService\enum;
use SuperPig\EedoCustomerService\events as systemEvents;

class Client
{

    /**
     * 身份验证
     *
     * @param $clientId
     * @param $data
     * @param $options
     * @return string
     * @throws \SuperPig\EedoCustomerService\exception\InvalidRequestException|\SuperPig\EedoCustomerService\Exception\LogicException
     */
    public static function __authentication($clientId, $data, $options)
    {
        // 最长只能26个字符
        switch (Config::get('eedo.logic.client.authentication', null)) {
            default:
                if (!isset($data['uuid'])) {
                    throw new InvalidRequestException('uuid parameter required', 50400);
                } else if (!is_string($data['uuid'])) {
                    throw new InvalidRequestException('uuid must be a string', 50400);
                }

                return addslashes(mb_substr($data['uuid'], 0, 16));
        }
    }

    /**
     * 鉴权完成后事件
     *
     * @param array $data 鉴权数据包
     */
    public static function __eventAfterAuthentication($data)
    {
        $uuid = $_SESSION['uuid'];

        // TODO:这里后续需要兼容多存储
        // 查询 OR 创建用户数据
        $clientInfo = Events::$mysqlDb->select('*')
            ->from(Helper::getDataTableName('client_users'))
            ->where('uuid = :uuid')
            ->bindValues(['uuid' => $uuid])
            ->row();
        if (empty($clientInfo)) {
            // 创建
            Events::$mysqlDb->insert(Helper::getDataTableName('client_users'))
                ->ignore(true)
                ->cols([
                    'uuid'  => $uuid,
                    'state' => enum\client_user\State::NORMAL,
                ])
                ->query();
            $clientInfo = Events::$mysqlDb->select('*')
                ->from(Helper::getDataTableName('client_users'))
                ->where('uuid = :uuid')
                ->bindValues(['uuid' => $uuid])
                ->row();
        }
        $_SESSION['client_info'] = $clientInfo;
        // 触发事件
        Helper::event(new systemEvents\client\Login($data));
        // 调度客服
        self::scheduler($data);
    }

    /**
     * 关闭事件
     */
    public static function __onClose() {
        $uuid = $_SESSION['uuid'];
        if(Gateway::isUidOnline($uuid) === 0 && isset($_SESSION['customer_service_id'])) {
            $csId = $_SESSION['customer_service_id'];
            Helper::clientUserAccessToCustomerService($csId, $uuid, [
                'state' => enum\reception_record\State::REMOVE,
                'store' => [
                    'drive'  => 'mysql',
                    'params' => [
                        'cu_id' => $_SESSION['client_info']['id'],
                    ],
                ],
            ]);
            // 触发事件
            Helper::event(new systemEvents\client\Logout());
            // 发送通知给管理员
            Gateway::sendToUid(
                $csId,
                Helper::formatResponseData(
                    '接待变动',
                    array( 'state' => enum\reception_record\State::REMOVE, 'uuid' => $uuid ),
                    'reception_change'
                )
            );
        }
    }

    /**
     * 发送消息
     *
     * @param $data
     * @throws \SuperPig\EedoCustomerService\Exception\LogicException
     * @throws \SuperPig\EedoCustomerService\exception\InvalidRequestException
     */
    public static function sendMessage($data)
    {
        // 检测消息数据包结构是否合法
        try {
            if (!isset($data['d'])) throw new Exception('缺少d数据', 50400);
            Helper::checkMsgDataStructure($data['d']);
        } catch (Exception $e) {
            throw new InvalidRequestException($e->getMessage(), $e->getCode());
        }

        $toId = $_SESSION['customer_service_id'];
        if (empty($toId)) throw new LogicException('未检测到您绑定的客服', 52601);
        $cuId = $_SESSION['client_info']['id'];
        $createdAt = date('Y-m-d H:i:s');

        // TODO:后期需要做成事件分发.将非主要逻辑抽离出去
        // 将消息写入数据库
        Events::$mysqlDb->insert(Helper::getDataTableName('chat_records'))
            ->cols([
                'from_user_type' => enum\chat_record\FromUserType::CLIENT,
                'to_user_type'   => enum\chat_record\ToUserType::SERVICE,
                'from_id'        => $cuId,
                'to'             => $toId,
                'content'        => json_encode($data['d']),
                'created_at'     => $createdAt,
            ])
            ->query();
        // 更新接待列表
        Events::$mysqlDb->query('UPDATE `'.Helper::getDataTableName('reception_records').'` SET cs_unread=cs_unread+1, cu_last_msg_at="'.date('Y-m-d H:i:s').'" WHERE cs_id='.$toId.' AND cu_id='.$cuId);
        // 触发事件
        Helper::event(new systemEvents\client\SendMessage($data));
        // 发送消息
        Gateway::sendToUid(
            $toId,
            Helper::formatResponseData(
                'new message',
                array(
                    'from'      => $cuId,
                    'to'        => $toId,
                    'is_system' => 0,
                    'd'         => $data['d'],
                    'created_at' => $createdAt
                ),
                'message_change',
                200
            )
        );
    }

    /**
     * 获得客服ID
     *
     * @param array $data 鉴权数据包
     * @return mixed|null
     */
    protected static function getCustomerServiceId($data)
    {
        // 获得上次正在对接的客服
        $lastReception = Events::$mysqlDb->select('*')
            ->from(Helper::getDataTableName('reception_records'))
            ->where('cu_id = :cu_id')
            ->where('state = :state')
            ->bindValues([
                'cu_id' => $_SESSION['client_info']['id'],
                'state' => enum\reception_record\State::ACCEPT,
            ])
            ->row();

        // 所有在线的客服
        $onlineCustomerServices = Events::$globalData->online_customer_services;
        if (empty($lastReception) === false && isset($onlineCustomerServices[$lastReception['cs_id']])) {
            // 分配上一次的客服
            return $lastReception['cs_id'];
        } else {
            // TODO:快排 & 优先分配最少的, 后期增加更多算法
            // 其他分配方式
            $rows = array();
            foreach($onlineCustomerServices as $key=>$value) {
                array_push($rows, array(
                    'id' => $key,
                    'count' => isset($value['rcpt_lists']) ? count($value['rcpt_lists']) : 0
                ));
            }
            $sortRows = Helper::quickSort($rows, 'count');
            return isset($sortRows[0]) ? $sortRows[0]['id'] : null;
        }
    }

    /**
     * 调度程序前的检查
     *
     * @param array $data 鉴权数据包
     * @return bool
     * @throws \SuperPig\EedoCustomerService\Exception\LogicException
     */
    protected static function checkBeforeScheduler($data)
    {
        $uuid                   = $_SESSION['uuid'];
        $onlineCustomerServices = Events::$globalData->online_customer_services;
        $uuidToCustomerServices = Events::$globalData->uuid_to_customer_services;
        if (!count($onlineCustomerServices) > 0) {
            throw new LogicException('没有在线客服', 52401);
        } else if (isset($uuidToCustomerServices[$uuid])) {
            throw new LogicException('禁止接入多个客服', 52405);
        }

        return true;
    }

    /**
     * 调度
     *
     * @param array $data 鉴权数据包
     */
    protected static function scheduler($data)
    {
        try {
            self::checkBeforeScheduler($data);
            $csId = self::getCustomerServiceId($data);
            if (empty($csId)) throw new LogicException('未找到适合的客服', 52406);

            // 调度
            Helper::clientUserAccessToCustomerService($csId, $_SESSION['uuid'], [
                'state' => enum\reception_record\State::ACCEPT,
                'store' => [
                    'drive'  => 'mysql',
                    'params' => [
                        'cu_id' => $_SESSION['client_info']['id'],
                    ],
                ],
            ]);
            $_SESSION['customer_service_id'] = $csId;

            // 发送通知给管理员
            Gateway::sendToUid(
                $csId,
                Helper::formatResponseData(
                    '接待变动',
                    array( 'state' => enum\reception_record\State::ACCEPT, 'uuid' => $_SESSION['uuid'] ),
                    'reception_change'
                )
            );
        } catch (LogicException $e) {
            Gateway::sendToCurrentClient(Helper::formatResponseData($e->getMessage(), [
                'from'      => null,
                'to'        => $_SESSION['uuid'],
                'is_system' => 1,
                'd'         => ['text' => $e->getMessage(), 'type' => 'text'],
            ], 'scheduler', $e->getCode()));
        }
    }
}