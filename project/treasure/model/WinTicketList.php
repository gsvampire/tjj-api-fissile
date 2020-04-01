<?php

namespace app\treasure\model;

use app\treasure\service\RedisService;
use think\Db;
use think\Log;

class WinTicketList extends Common
{
    const SIGN = 1;
    const FOOD_HOUSE = 2;
    const SHARE      = 3;
    const ORDER      = 4; // 4-充值送券 5-购买商品送券 统称下单
    const BUY        = 5;

    const IS_REFUND_YES = 1;
    const IS_INVALID_YES = 1;
    public static $type_text = [
        self::SIGN       => "签到",
        self::FOOD_HOUSE => "美食屋",
        self::SHARE      => "分享",
        self::ORDER      => "下单",
        self::BUY        => "下单"
    ];

    //获取账号剩余夺宝券数量 过期时间大于今天 且未退款
    static function getAccount($user_id)
    {
        $overdue_time = strtotime(date("Y-m-d", time()));
        $sql = "SELECT sum(left_num) as num 
                FROM lb_win_ticket_list where user_id = {$user_id} and overdue_time > {$overdue_time} and is_refund = 0";
        $result =  Db::query($sql);
        $ticket = $result[0]['num'] ?? 0;
        return $ticket;
    }

    /**
     * 获取 7天内有效 券列表
     * @param $user_id
     * @return mixed
     */
    static function getTicketList($user_id)
    {
        $key   ="db:ticket:list:{$user_id}";
        $redis = new RedisService();
        $date = strtotime(date("Y-m-d", time()));
        $last_date = strtotime(date("Y-m-d", strtotime("-6 days")));

        $data = $redis->get($key);
        if (!$data) {
            $sql = "SELECT id,get_num,left_num,overdue_time,`type`,create_time,is_refund 
                FROM lb_win_ticket_list where user_id = {$user_id} and overdue_time >={$last_date} 
                order by if(overdue_time >{$date},0,1)";


            $list = Db::query($sql);

            $data['list'] = $list;

            $redis->set($key, $data);
        }

        return $data;

    }

    /**
     * 获取券明细信息
     * @param $user_id
     * @return array
     */
    static function getTicketInfo($user_id)
    {
        $today = date("Y-m-d",time());
        $key   = "db:share:dialog:user_id:{$user_id}:date:{$today}";
        $redis = new RedisService();
        $data  = $redis->get($key);
        if(!$data)
        {
            $sql = "SELECT is_read,id FROM lb_win_ticket_list 
                    WHERE user_id = {$user_id} and create_time >= '{$today}' and type = ".self::SHARE." ORDER BY id DESC LIMIT 1";

            $result = Db::query($sql);

            $data['info'] = $result[0] ?? [];

            $redis->set($key,$data,60);
        }


        return $data['info'];
    }

    /**
     * 获取券明细信息
     * @param $user_id
     * @return array
     */
    static function getTicketOrderNum($user_id)
    {
        $date = date("Y-m-d",time());
        $key   = "db:share:order:user_id:{$user_id}:date:{$date}";
        $redis = new RedisService();
        $time  = $redis->get($key);

        if(!$time)
        {
            $redis->set($key,$date,86400);
        }else{
            $date = $redis->get($key);
        }

        $sql = "SELECT sum(get_num) as num FROM lb_win_ticket_list 
            WHERE user_id = {$user_id} and create_time >= '{$date}' and  is_refund = 0 and (type = ".self::ORDER ." or type = ".self::BUY ." ) ";

        $info = Db::query($sql);

        $redis->set($key,date("Y-m-d H:i:s",time()),86400);

        return $info[0]['num'] ?? 0;
    }

    /**
     * 更新状态已读
     * @param $id
     * @return int
     *
     */
    static function updateTicketInfo($id)
    {
        $sql = "update  lb_win_ticket_list set is_read = 1 WHERE id = {$id} limit 1";

        return Db::execute($sql);
    }

    /**
     * 夺宝券明细表数据写入
     * @param $user_id : 用户id
     * @param $num : 本次获得夺宝券数量
     * @param $ticketIndate : 失效天数
     * @param $type : 夺宝券来源
     * @param $opts : 扩展参数
     * @return mixed : 添加数据的主键
     */
    public function ticket_insert($user_id, $num, $ticketIndate, $type, $opts = [])
    {
        $order_id = $opts['order_id'] ?? '';
        $ticketModel = $this->dataModel($this::TICKET_LIST);
        $data = array(
            'user_id' => $user_id,
            'get_num' => $num,
            'left_num' => $num,
            'order_id' => $order_id,
            'overdue_time' => 86400 * $ticketIndate + strtotime(date('Y-m-d')),
            'type' => $type,
            'is_read' => 0
        );
        $result = $this->insert_data_one($ticketModel, $data);
        return $result;
    }

    /**
     * ticket表数据更新
     * @param $id
     * @param $num
     * @return mixed ：受影响的条数，无修改则返回0
     */
    public function ticket_update($id, $num)
    {
        $accountModel = $this->dataModel($this::TICKET_LIST);
        $where = array(
            'id' => $id,
            'is_refund' => 0
        );
        $data = array(
            'key' => 'left_num',
            'num' => $num
        );
        $result = $this->update_data(3, $accountModel, $where, $data);
        return $result;
    }

    /**
     * 查询用户有效夺宝券，排除失效
     * @param $user_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function ticket_unused($user_id)
    {
        $where = array(
            'user_id' => $user_id,
            'overdue_time' => ['gt', strtotime(date("Y-m-d", time()))],
            'is_refund' => 0,
            'left_num' => ['gt',0]
        );
        $field = "id,left_num";
        return $this->dataModel($this::TICKET_LIST)->field($field)->where($where)->order("id")->select();
    }

    /**
     * 查询今日发放券总量
     * @return float|int
     */
    public function daily_total_ticket()
    {
        $where = array(
            'create_time' => ['gt', date('Y-m-d')]
        );
        return $this->dataModel($this::TICKET_LIST)->where($where)->sum("get_num");
    }


    public function take_back_ticket($order_id)
    {
        Db::startTrans();
        try {
            $ticket_list_model = $this->dataModel($this::TICKET_LIST);
            $tickets = $ticket_list_model->field('id')->where(['order_id' => $order_id])->select();
            $ticket_ids = [0];
            foreach ($tickets as $ticket) {
                $ticket_ids[] = $ticket['id'];
            }
            // 订单发放的夺宝券失效
            $ticket_list_model->where(['id'=>['in', $ticket_ids]])->update(['is_refund' => self::IS_REFUND_YES]);
            // 使用夺宝券产生的幸运码失效
            $lucky_num_model = $this->dataModel($this::LUCKY_NUM);
            $lucky_num_model->where(['ticket_id'=>['in', $ticket_ids]])->update(['is_invalid' => self::IS_INVALID_YES]);
            Db::commit();
            return true;
        } catch (\Exception $exception) {
            Db::rollback();
            Log::info($exception->getMessage());
            return false;
        }
    }

    /**
     * 用户当日账户已获得夺宝券数量
     * @param $user_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function today_account($user_id){
        $where = array(
            'user_id' => $user_id,
            'create_time' => ['egt', date("Y-m-d",time())],
            'is_refund' => 0
        );
        $field = "id,sum(get_num) as ticket";
        return $this->dataModel($this::TICKET_LIST)->field($field)->where($where)->find();
    }
}