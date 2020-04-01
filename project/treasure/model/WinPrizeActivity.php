<?php

namespace app\treasure\model;
use app\treasure\service\RedisService;
use think\Db;
use think\Model;

class WinPrizeActivity extends Model
{
    protected $resultSetType = 'collection';
    const TABLE_NAME = "lb_win_prize_activity";
    const ACTIVITY = 'win_prize_activity';//活动表
    const STATUS_ING = 1;
    const STATUS_WAIT = 2;
    const STATUS_OVER = 3;

    public static $status_text = [
        self::STATUS_ING  => '进行中',
        self::STATUS_WAIT => '待揭晓',
        self::STATUS_OVER => '已结束',
    ];

    const FORBID_YES = 1;
    const FORBID_NO  = 0;
    public static $forbid_text = [
        self::FORBID_NO  => '正常',
        self::FORBID_YES => '作废'
    ];

    /**
     * 获取所有活动数据源  排序越小越靠前
     * @return mixed
     */
    public static function getAll()
    {
        $now_time = self::getNowTime();
        $key = "db:activity:all:list";
        // 缓存逻辑
        $redis = new RedisService();
        $list  = $redis->get($key);
        if(!$list)
        {
            $sql = "SELECT
                a.attend_ticket,
                a.ticket,
                a.ticket_admin,
                a.goods_id,
                a.id as activity_id,
                a.status,
                a.is_forbid,
                o.sort
            FROM
                lb_win_prize_activity a 
            LEFT JOIN lb_win_prize_goods_order o ON a.goods_id = o.goods_id 
            WHERE
                (a.begin_time >= {$now_time} and a.is_del = 0)
                OR ( a.begin_time < {$now_time} and a.status = 1 AND a.is_forbid = 0 and a.is_del = 0 ) 
            ORDER BY o.sort ASC,o.update_time  DESC";

            $list = Db::query($sql);

            $redis->set($key,$list);
        }

        return $list;
    }


    //获取产品 参与夺宝券数量
    static function getAttendTicketNum($active_id)
    {
        $active_id = (array) $active_id;
        if(!$active_id)
        {
            return [];
        }
        $whereId = implode(',',$active_id);

        $sql = "SELECT
                attend_ticket
            FROM
                lb_win_prize_activity 
            WHERE
                id in ( {$whereId} )";

        $list = Db::query($sql);

        return $list;
    }


    /**
     * 获取所有活动数据源  价格越高越靠前
     * @return mixed
     */
    public static function getAllByPrice()
    {
        $now_time = self::getNowTime();
        $key = "db:activity:all:price:list";
        // 缓存逻辑
        $redis = new RedisService();
        $list  = $redis->get($key);
        if(!$list)
        {
            $sql = "SELECT
                a.attend_ticket,
                a.ticket,
                a.ticket_admin,
                a.goods_id,
                a.id as activity_id,
                a.status,
                a.is_forbid,
                g.price,
                g.specs,
                g.img,
                g.goods_name
            FROM
                lb_win_prize_activity a 
            LEFT JOIN lb_win_prize_goods g ON a.goods_id = g.id 
            WHERE
                (a.begin_time >= {$now_time} and a.is_del = 0)
                OR ( a.begin_time < {$now_time} AND a.status = 1 AND a.is_forbid = 0 and a.is_del = 0) 
            ORDER BY g.price DESC,g.id DESC";

            $list = Db::query($sql);

            $redis->set($key,$list);
        }

        return $list;
    }

    /**
     * 获取所有活动数据源  按剩余参与票数  越少越靠前
     * @return mixed
     */
    public static function getAllByLeftTicket()
    {
        $now_time = self::getNowTime();
        $key = "db:activity:all:left:ticket";
        // 缓存逻辑
        $redis = new RedisService();
        $list  = $redis->get($key);
        if(!$list)
        {
            $sql = "SELECT
                a.attend_ticket,
                a.ticket,
                a.ticket_admin,
                a.goods_id,
                a.id as activity_id,
                a.status,
                a.is_forbid,
                g.price,
                g.specs,
                g.img,
                g.goods_name
            FROM
                lb_win_prize_activity a 
            LEFT JOIN lb_win_prize_goods g ON a.goods_id = g.id 
            WHERE
                (a.begin_time >= {$now_time} and a.is_del = 0)
                OR ( a.begin_time < {$now_time} AND a.status = 1 AND a.is_forbid = 0 and a.is_del = 0) 
            ORDER BY left_over_ticket ASC ,g.price DESC,g.id DESC";

            $list = Db::query($sql);

            $redis->set($key,$list);
        }

        return $list;
    }


    /**
     * 根据活动id 获取 活动信息
     * @param $activity_id  array
     * @return array|mixed
     */
    public static function getActivityInfoByIds($activity_id)
    {
        $activity_id = (array) $activity_id;
        if(!$activity_id)
        {
            return [];
        }
        $key = "db:activity:info:id:".implode("-",$activity_id);
        //缓存
        $redis = new RedisService();
        $activity_info = $redis->get($key);
        if(!$activity_info)
        {
            $whereId = implode(',',$activity_id);
            $sql = "SELECT
                id,
                ticket,
                ticket_admin,
                attend_ticket,
                status,
                is_forbid
            FROM
                lb_win_prize_activity 
            WHERE id in ( {$whereId} )";

            $list = Db::query($sql);
            $activity_info = [];
            array_map(function($item) use (&$activity_info){
                $activity_info[$item['id']] = $item;
            },$list);

            $redis->set($key,$activity_info);
        }


        return $activity_info;
    }


    /**
     * 根据商品id 获取 进行中活动
     * @param $goods_id array|signel
     * @return array|mixed
     */
    public static function getActivityInfoByGoodsId($goods_id)
    {
        $goods_id = (array) $goods_id;
        if(!$goods_id)
        {
            return [];
        }
        $key = "db:activity:info:goods_id:".implode("-",$goods_id);
        //缓存
        $redis = new RedisService();
        $activity_info = $redis->get($key);
        if(!$activity_info)
        {
            $whereId = implode(',',$goods_id);
            $sql = "SELECT
                id,
                goods_id
            FROM
                lb_win_prize_activity 
            WHERE goods_id in ( {$whereId} ) AND status = ".self::STATUS_ING ." AND is_forbid = ".self::FORBID_NO;

            $list = Db::query($sql);
            $activity_info = [];
            array_map(function($item) use (&$activity_info){
                $activity_info[$item['goods_id']] = $item;
            },$list);

            $redis->set($key,$activity_info);
        }


        return $activity_info;
    }



    static function getNowTime()
    {
        $time = date("Y-m-d",time());
        return strtotime($time);
    }

    /**
     * 活动数据获取
     * @param $activity_id : 活动id
     * @return mixed : 一维数组
     */
    public function activity_info($activity_id)
    {
        $where = array(
            'activity.id' => $activity_id,
        );
        $field = "activity.status,activity.begin_time,activity.is_forbid,activity.goods_id,activity.attend_ticket,activity.ticket,activity.ticket_admin,goods.pre_key,goods.img_turn,goods.goods_name,goods.price,goods.specs";
        return db($this::ACTIVITY)->alias("activity")->field($field)->
        join('lb_win_prize_goods goods', 'activity.goods_id = goods.id')->where($where)->find();
    }

    /**
     * 活动已使用券总数增加
     * @param $activity_id : 活动id
     * @param $num : 数量
     * @param $left_over_ticket : 距离活动结束还差的券
     * @return mixed ：受影响的条数，无修改则返回0
     */
    public function attend_ticket_inc($activity_id, $num,$left_over_ticket)
    {
        $model = $this->db($this::ACTIVITY);
        $where = array(
            'id' => $activity_id,
        );
        $data = array(
            'left_over_ticket' => $left_over_ticket
        );
        return $model->where($where)->inc('attend_ticket',$num)->update($data);
    }

    /**
     * 活动表数据更新（达到开奖数量时）
     * @param $activity_id
     * @param $num
     * @param $left_over_ticket : 距离活动结束还差的券
     * @return mixed ：受影响的条数，无修改则返回0
     */
    public function activity_update($activity_id, $num,$left_over_ticket)
    {
        $model = $this->db($this::ACTIVITY);
        $where = array(
            'id' => $activity_id,
        );
        $data = array(
            'status' => 2,
            'left_over_ticket' => $left_over_ticket
        );
        return $model->where($where)->inc('attend_ticket',$num)->update($data);
    }

    /**
     * 查询商品最新进行中的活动
     * @param $goods_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function new_activity($goods_id){
        return Db::table($this::TABLE_NAME)
            ->field("id,begin_time")
            ->where('goods_id',intval($goods_id))
            ->where('status',1)
            ->order("id desc")
            ->find();
    }
}