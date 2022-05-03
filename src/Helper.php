<?php
/* =============================================================================#
# Description: 助手
#============================================================================= */

namespace SuperPig\EedoCustomerService;


use Exception;
use SuperPig\EedoCustomerService\enum;
use SuperPig\EedoCustomerService\events\Event;
use think\facade\Config;

class Helper
{
    /**
     * 格式化响应数据
     *
     * @param        $message
     * @param array  $data
     * @param string $runCmd
     * @param int    $code
     * @param array  $options
     * @return false|string
     */
    public static function formatResponseData($message, $data = [], $runCmd = '', $code = 0, $options = [])
    {
        return json_encode([
            'message' => $message,
            'data'    => $data,
            'run_c'   => $runCmd,
            'code'    => $code,
        ]);
    }

    /**
     * 检测数据包结构
     *
     * @param $data
     * @throws \Exception
     */
    public static function checkMsgDataStructure($data)
    {
        if (!isset($data['type'])) throw new Exception('缺少消息类型');
        $msgType = $data['type'];
        $msgTypeList = array('text');
        if(!in_array($msgType, $msgTypeList)) throw new Exception('不存在的消息类型');

        // 检测对应消息类型的具体数据字段
        switch($msgType) {
            case 'text':
                    if(!isset($data['text'])) throw new Exception('文本不能为空');
                    if(mb_strlen($data['text']) > 600) throw new Exception('一次性最多发送600个字');
                break;
        }
    }

    /**
     * 接入客服 (数据落地,强行写入,支持并发写入)
     * 使用前提Events类被正常初始化 & 不受调用环境影响
     *
     * @param int        $csId 客服ID
     * @param string|int $uuid 客户端UUID
     * @param array      $options
     */
    public static function clientUserAccessToCustomerService($csId, $uuid, $options = [])
    {
        $options = array_merge([
            // 状态 enum\reception_record\State
            'state' => enum\reception_record\State::REMOVE,
            // 当使用其他存储时需要传入, TODO: 默认使用mysql,后期扩展
            'store' => [
                // 驱动
                'drive'  => 'mysql',
                // 参数
                'params' => [
                    // 必填参数
                    'cu_id' => null,
                ],
            ],
        ], $options);

        // 写入到内存
        do {
            // 直接覆盖,不考虑已存在的
            $newUuidToCustomerServices = $oldUuidToCustomerServices = Events::$globalData->uuid_to_customer_services;
            if ($options['state'] === enum\reception_record\State::ACCEPT) {
                $newUuidToCustomerServices[$uuid] = array();
            } else if ($options['state'] === enum\reception_record\State::REMOVE) {
                if (isset($newUuidToCustomerServices[$uuid])) {
                    unset($newUuidToCustomerServices[$uuid]);
                } else {
                    break;
                }
            }
        } while (!Events::$globalData->cas('uuid_to_customer_services', $oldUuidToCustomerServices, $newUuidToCustomerServices));

        do {
            $newOlCs = $oldOlCs = Events::$globalData->online_customer_services;
            if(!isset($newOlCs[$csId])) $newOlCs[$csId] = array();
            if(!isset($newOlCs[$csId]['rcpt_lists'])) $newOlCs[$csId]['rcpt_lists'] = array();

            if ($options['state'] === enum\reception_record\State::ACCEPT) {
                if(!isset($newOlCs[$csId]['rcpt_lists'][$uuid])) $newOlCs[$csId]['rcpt_lists'][$uuid] = array();
            } else if ($options['state'] === enum\reception_record\State::REMOVE) {
                if(isset($newOlCs[$csId]['rcpt_lists'][$uuid])) {
                    unset($newOlCs[$csId]['rcpt_lists'][$uuid]);
                } else {
                    break;
                }
            }
        } while (!Events::$globalData->cas('online_customer_services', $oldOlCs, $newOlCs));


        // 写入到mysql
        if ($options['store']['drive'] === 'mysql') {
            $cuId                     = $options['store']['params']['cu_id'];
            $receptionRecordTableName = self::getDataTableName('reception_records');
            $sql                      = "SELECT id,cs_id,cu_id FROM " . $receptionRecordTableName . " WHERE cs_id=${csId} AND cu_id=${cuId}";
            // 保证数据存在
            $row = Events::$mysqlDb->row($sql);
            if (empty($row)) {
                Events::$mysqlDb->insert($receptionRecordTableName)
                    ->ignore(true)
                    ->cols([
                        'cs_id' => $csId,
                        'cu_id' => $cuId,
                    ])
                    ->query();
                $row = Events::$mysqlDb->row($sql);
            }

            // 变更状态
            if ($options['state'] === enum\reception_record\State::ACCEPT) {
                // 将所有其他标记为移除
                Events::$mysqlDb->update($receptionRecordTableName)
                    ->cols(['state' => enum\reception_record\State::REMOVE])
                    ->where('state=' . enum\reception_record\State::ACCEPT . ' AND cu_id = ' . $cuId . ' AND id != ' . $row['id'])
                    ->query();

                // 标记为接待中
                Events::$mysqlDb->update($receptionRecordTableName)
                    ->cols(['state' => enum\reception_record\State::ACCEPT])
                    ->where('state=' . enum\reception_record\State::REMOVE . ' AND cu_id = ' . $cuId . ' AND id = ' . $row['id'])
                    ->query();
            } else if ($options['state'] === enum\reception_record\State::REMOVE) {
                // 标记为移除
                Events::$mysqlDb->update($receptionRecordTableName)
                    ->cols(['state' => enum\reception_record\State::REMOVE])
                    ->where('state=' . enum\reception_record\State::ACCEPT . ' AND cu_id = ' . $cuId . ' AND id = ' . $row['id'])
                    ->query();
            }
        }
    }

    /**
     * 获得数据表名字
     *
     * @param $name
     * @return string
     */
    public static function getDataTableName($name)
    {
        return Events::$mysqlConfig['table_prefix'] . $name;
    }

    /**
     * 快速排序
     *
     * @param $arr
     * @param $countKey
     * @return array|mixed
     */
    public static function quickSort($arr, $countKey)
    {
        if (!count($arr) > 0) return $arr;

        $middle     = $arr[0];
        $leftArray  = [];
        $rightArray = [];

        // 让它成为中间值
        for ($i = 1; $i < count($arr); $i++) {
            if ($arr[$i][$countKey] > $middle[$countKey]) {
                $rightArray[] = $arr[$i];
            } else {
                $leftArray[] = $arr[$i];
            }
        }

        $leftArray = self::quickSort($leftArray, $countKey);
        array_push($leftArray, $middle);
        $rightArray = self::quickSort($rightArray, $countKey);
        return array_merge($leftArray, $rightArray);
    }

    /**
     * 触发事件
     *
     * @param \SuperPig\EedoCustomerService\events\Event $event
     */
    public static function event(Event $event) {
        $className = get_class($event);
        if(isset(Events::$eventListen[$className])) {
            foreach(Events::$eventListen[$className] as $item) {
                $classAndMethod = explode('@', $item);
                forward_static_call_array(array(
                    $classAndMethod[0],
                    $classAndMethod[1]
                ), array($event));
            }
        }
    }
}