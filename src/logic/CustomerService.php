<?php
/* =============================================================================#
# Description: 客服逻辑
#============================================================================= */

namespace SuperPig\EedoCustomerService\logic;


use Exception;
use GatewayWorker\Lib\Gateway;
use SuperPig\EedoCustomerService\Events;
use SuperPig\EedoCustomerService\exception\AuthenticationException;
use SuperPig\EedoCustomerService\exception\InvalidRequestException;
use SuperPig\EedoCustomerService\exception\LogicException;
use SuperPig\EedoCustomerService\Helper;
use SuperPig\EedoCustomerService\enum;
use SuperPig\EedoCustomerService\events as systemEvents;

class CustomerService
{
    /**
     * @param $clientId
     * @param $data
     * @param $options
     * @return mixed
     * @throws \SuperPig\EedoCustomerService\exception\InvalidRequestException|\SuperPig\EedoCustomerService\exception\AuthenticationException
     */
    public static function __authentication($clientId, $data, $options) {
        if(!isset($data['account']) && isset($data['password'])) throw new InvalidRequestException('请输入账号和密码', 50400);
        $account = $data['account'];
        $password = $data['password'];

        // TODO:存储扩展
        $row = Events::$mysqlDb->select('*')
            ->from(Helper::getDataTableName('customer_service_accounts'))
            ->where('account = :account')
            ->bindValues([
                'account' => $account,
            ])
            ->row();
        if(empty($row)) throw new AuthenticationException('账号不存在', 51501);
        if(!password_verify($password, $row['password'])) throw new AuthenticationException('密码错误', 51502);

        $_SESSION['customer_service_info'] = $row;
        return $row['id'];
    }

    /**
     * 鉴权完成后事件
     *
     * @param array $data 鉴权数据包
     */
    public static function __eventAfterAuthentication($data)
    {
        $uuid = $_SESSION['uuid'];

        // 绑定
        Gateway::bindUid($_SESSION['client_id'], $uuid);
        // 标记在线
        do{
            $newOnlineCustomerServices = $oldOnlineCustomerServices = Events::$globalData->online_customer_services;
            if(!isset($newOnlineCustomerServices[$uuid])) {
                $newOnlineCustomerServices[$uuid] = array(
                    // 接待列表
                    'rcpt_lists' => array()
                );
            }
        } while(!Events::$globalData->cas('online_customer_services', $oldOnlineCustomerServices, $newOnlineCustomerServices));
        // 触发事件
        Helper::event(new systemEvents\client\Login($data));
    }

    /**
     * 关闭事件
     */
    public static function __onClose() {
        // 判断是否下线
        $uuid = $_SESSION['uuid'];
        if(Gateway::isUidOnline($uuid) === 0) {
            do {
                $newOnlineCustomerServices = $oldOnlineCustomerServices = Events::$globalData->online_customer_services;
                if(isset($newOnlineCustomerServices[$uuid])) {
                    unset($newOnlineCustomerServices[$uuid]);
                } else {
                    break;
                }
            } while (!Events::$globalData->cas('online_customer_services', $oldOnlineCustomerServices, $newOnlineCustomerServices));
            // 触发事件
            Helper::event(new systemEvents\client\Logout());
            // 向所有正在沟通的用户发送离线消息
            foreach($oldOnlineCustomerServices[$uuid]['rcpt_lists'] as $key=>$value) {
                // 发送通知给管理员
                Gateway::sendToUid(
                    $key,
                    Helper::formatResponseData(
                        '客服下线',
                        array(),
                        'customer_service_offline'
                    )
                );
            }
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
            if (!isset($data['to'])) throw new Exception('缺少to参数', 50400);
            Helper::checkMsgDataStructure($data['d']);
        } catch (Exception $e) {
            throw new InvalidRequestException($e->getMessage(), $e->getCode());
        }

        $clientUserUuid = $data['to'];
        $fromId = $_SESSION['uuid'];
        $createdAt = date('Y-m-d H:i:s');

        // TODO:后期需要做成事件分发.将非主要逻辑抽离出去
        // 将消息写入数据库
        $toId = Events::$mysqlDb->select('id')
            ->from(Helper::getDataTableName('client_users'))
            ->where('uuid=:uuid')
            ->bindValues(array(
                'uuid' => $clientUserUuid
            ))
            ->single();
        if(empty($toId)) throw new LogicException('接收用户不存在', 51401);

        Events::$mysqlDb->insert(Helper::getDataTableName('chat_records'))
            ->cols([
                'from_user_type' => enum\chat_record\FromUserType::SERVICE,
                'to_user_type'   => enum\chat_record\ToUserType::CLIENT,
                'from_id'        => $fromId,
                'to_id'          => $toId,
                'content'        => json_encode($data['d']),
                'created_at'     => date('Y-m-d H:i:s'),
            ])
            ->query();
        // 更新接待列表
        Events::$mysqlDb->query('UPDATE `'.Helper::getDataTableName('reception_records').'` SET cu_unread=cu_unread+1, cs_last_msg_at="'.date('Y-m-d H:i:s').'" WHERE cs_id='.$fromId.' AND cu_id='.$toId);
        // 触发事件
        Helper::event(new systemEvents\client\SendMessage($data));
        // 发送消息
        Gateway::sendToUid(
            $clientUserUuid,
            Helper::formatResponseData(
                'new message',
                array(
                    'from'      => $fromId,
                    'to'        => $clientUserUuid,
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
     * 聊天记录
     *
     * @param $data
     */
    public static function chatRecords($data) {
        // TODO:后期要将数据库解耦
        $page = isset($data['p']) && is_int($data['p']) ? $data['p'] : 1;
        $customerServiceId = $_SESSION['customer_service_info']['id'];
        $chatRecordsTable = Helper::getDataTableName('chat_records');

        $rows = Events::$mysqlDb->select("*")
            ->from($chatRecordsTable)
            ->where('from_user_type='.enum\chat_record\FromUserType::SERVICE.' AND from_id=:from_id')
            ->orWhere('to_user_type='.enum\chat_record\ToUserType::SERVICE.' AND to_id=:to_id')
            ->bindValues(array(
                'from_id' => $customerServiceId,
                'to_id' => $customerServiceId,
            ))
           ->orderByDESC(array(
                $chatRecordsTable.'.id'
            ))
            ->setPaging(25)
            ->page($page)
            ->query();

        // 对数据进行处理
        $resultData = array();
        $customerServiceUser = array();
        $clientUser = array();
        $clientUsersTable = Helper::getDataTableName('client_users');
        $customerServiceAccountTable = Helper::getDataTableName('customer_service_accounts');

        foreach($rows as $item) {
            // 返回数据
            $result = array(
                'from' => array(),
                'to'   => array(),
                'from_id' => $item['from_id'],
                'to_id' => $item['to_id'],
                'from_user_type' => $item['from_user_type'],
                'to_user_type' => $item['to_user_type'],
                'content' => $item['content'],
                'created_at' => $item['created_at'],
            );

            // 赋值发送方信息
            $fromId = $item['from_id'];
            switch ($item['from_user_type']) {
                case enum\chat_record\FromUserType::CLIENT:
                    if(!isset($clientUser[$fromId])) {
                        $clientUser[$fromId] = Events::$mysqlDb->select('*')->from($clientUsersTable)->where('id=:id')->bindValues(array( 'id' => $fromId ))->row();
                    }
                    $result['from'] = $clientUser[$fromId]['info'];
                    break;
                case enum\chat_record\FromUserType::SERVICE:
                    if(!isset($customerServiceUser[$fromId])) {
                        $customerServiceUser[$fromId] = Events::$mysqlDb->select('*')->from($customerServiceAccountTable)->where('id=:id')->bindValues(array( 'id' => $fromId ))->row();
                    }
                    $result['from'] = $customerServiceUser[$fromId]['info'];
                    break;
            }

            // 接收方信息
            $toId = $item['to_id'];
            switch ($item['to_user_type']) {
                case enum\chat_record\ToUserType::CLIENT:
                    if(!isset($clientUser[$toId])) {
                        $clientUser[$toId] = Events::$mysqlDb->select('*')->from($clientUsersTable)->where('id=:id')->bindValues(array( 'id' => $toId ))->row();
                    }
                    $result['to'] = $clientUser[$toId]['info'];
                    break;
                case enum\chat_record\ToUserType::SERVICE:
                    if(!isset($customerServiceUser[$toId])) {
                        $customerServiceUser[$toId] = Events::$mysqlDb->select('*')->from($customerServiceAccountTable)->where('id=:id')->bindValues(array( 'id' => $toId ))->row();
                    }
                    $result['to'] = $customerServiceUser[$toId]['info'];
                    break;
            }

            array_push($resultData, $result);
        }

        Gateway::sendToCurrentClient(
            Helper::formatResponseData(
                '聊天记录',
                $resultData,
                'chat_records',
                200
            )
        );
    }

    /**
     * 接待列表
     *
     * @param $data
     */
    public static function receptionRecords($data) {
        // TODO:后期需要将数据库解耦
        $page = isset($data['p']) && is_int($data['p']) ? $data['p'] : 1;
        // 暂时先都从数据库读
        $csId = $_SESSION['uuid'];
        $receptionRecordsTable = Helper::getDataTableName('reception_records');
        $clientUsersTable = Helper::getDataTableName('client_users');
        $rows = Events::$mysqlDb->select("
                ${receptionRecordsTable}.id,
                ${receptionRecordsTable}.cu_id,
                ${receptionRecordsTable}.state,
                ${receptionRecordsTable}.cs_unread as unread,
                ${receptionRecordsTable}.cu_last_msg_at,
                ${clientUsersTable}.uuid,
                ${clientUsersTable}.info
            ")
            ->from($receptionRecordsTable)
            ->innerJoin($clientUsersTable, $receptionRecordsTable.'.cu_id = '.$clientUsersTable.'.id')
            ->where('cs_id=:cs_id')
            ->orderByDESC(array(
                $receptionRecordsTable.'.cu_last_msg_at',
                $receptionRecordsTable.'.id'
            ))
            ->page($page)
            ->bindValues(array(
                'cs_id' => $csId
            ))
            ->query();

        Gateway::sendToCurrentClient(
            Helper::formatResponseData(
                '接待列表',
                $rows,
                'reception_records',
                200
            )
        );
    }
}