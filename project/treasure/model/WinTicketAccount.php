<?php
namespace app\treasure\model;

use think\Db;
use think\Model;

class WinTicketAccount extends Common{

    /**
     * 获取账号剩余数量
     * @param $user_id
     * @return mixed
     */
//    static function account($user_id)
//    {
//        if(!$user_id)
//        {
//            return 0;
//        }
//        $overdue_time = strtotime(date("Y-m-d",time()));
//        $sql = "SELECT sum(ticket) as ticket,sum(use_ticket) as use_ticket FROM lb_win_ticket_account
//                WHERE user_id = {$user_id} and overdue_time > {$overdue_time}";
//
//        $result =  Db::query($sql);
//        $ticket = $result[0]['ticket'] ?? 0;
//        $use_ticket = $result[0]['use_ticket'] ?? 0;
//        return max(0,($ticket - $use_ticket));
//    }

    /**
     * 用户当日账户信息
     * @param $user_id
     * @return mixed/一维数组
     */
//    public function today_account($user_id)
//    {
//        $where_account = array(
//            'user_id' => $user_id,
//            'create_time' => ['gt', date('Y-m-d', time())]
//        );
//        $field = "id,user_id,ticket,overdue_time,use_ticket";
//        return $this->dataModel($this::ACCOUNT)->field($field)->where($where_account)->find();
//    }

    /**
     * 用户当前夺宝券余额
     * @param $user_id : 用户id
     * @return int : 余额
     */
//    public function ticket_balance($user_id)
//    {
//        $where_account = array(
//            'user_id' => $user_id,
//            'overdue_time' => ['gt', time()]
//        );
//        return $this->dataModel($this::ACCOUNT)->where($where_account)->sum("ticket - use_ticket");
//    }

    /**
     * 账户表数据写入
     * @param $user_id : 用户id
     * @param $num : 本次获得券数量
     * @param $ticketIndate : 失效天数
     * @return mixed : 添加数据的主键
     */
//    public function account_insert($user_id, $num, $ticketIndate)
//    {
//        $accountModel = $this->dataModel($this::ACCOUNT);
//        $data = array(
//            'user_id' => $user_id,
//            'ticket' => $num,
//            'overdue_time' => 86400 * $ticketIndate + strtotime(date('Y-m-d'))
//        );
//        $result = $this->insert_data_one($accountModel, $data);
//        return $result;
//    }

    /**
     * 账户表数据更新
     * @param $user_id : 用户id
     * @param $num : 本次获得券数量
     * @param $type : 更新方式，2-自增，3-自减
     * @param $key : 更新字段
     * @return mixed : 受影响的条数，无修改则返回0
     */
    public function account_update($user_id, $num, $type,$key = 1)
    {
        $accountModel = $this->dataModel($this::ACCOUNT);
        $where = array(
            'user_id' => $user_id,
            'create_time' => ['gt', date('Y-m-d')],
            'overdue_time' => ['gt', strtotime(date('Y-m-d'))]
        );
        $data = array(
            'key' => $key == 1 ? 'ticket' : 'use_ticket',
            'num' => $num
        );
        $result = $this->update_data($type, $accountModel, $where, $data);
        return $result;
    }
}