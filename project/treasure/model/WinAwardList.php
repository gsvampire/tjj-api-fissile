<?php
namespace app\treasure\model;
use app\treasure\service\RedisService;
use think\Db;

class WinAwardList extends \think\Model
{
    const TABLE_NAME = "lb_win_attend_activity";
    const AWARD_LIST = "lb_win_award_list";

    //最新30内开奖记录
    static function getAwardList($page)
    {
        $page_size = config('page_size');
        $offset    = ($page - 1) * $page_size;
       // $key = "db:newest:award:list:{$page}";
        //$redis = new RedisService();
        //$list  = $redis->get($key);
        $time  = strtotime(date("Y-m-d",time())) - 30*24*3600;// 30天以内
        //if(!$list){
            $sql = "SELECT activity_id,goods_id,award_time,award_num,nick_name,avatar,fight_coupon_num,attend_user,activity_time  
                FROM lb_win_award_list where award_time >= $time and is_del = 0   ORDER BY award_time DESC limit {$offset},{$page_size}";


            $sql_count = "SELECT count(*) as num FROM lb_win_award_list where award_time >= {$time} and is_del = 0";
            $data = Db::query($sql);
            $count = Db::query($sql_count);

            $list['list'] = $data;
            $list['count']= $count[0]['num'] ?? 0;

           // $redis->set($key,$list);
       // }


        return $list;
    }


    //获取用户 某活动是否中奖
    static function getAwardInfo($user_id,$activity_id)
    {
        $activity_id = (array) $activity_id;
        if(!$activity_id)
        {
            return [];
        }
        $key = "db:award:info:{$user_id}:".implode('-',$activity_id);
        $whereId = implode(',',$activity_id);
        $redis = new RedisService();
        $data = $redis->get($key);
        if(!$data){
            $sql = "SELECT activity_id,award_time
                FROM lb_win_award_list where user_id = {$user_id} and activity_id in ( {$whereId} )";

            $list = Db::query($sql);
            $award_info = [];
            array_map(function($item) use (&$award_info){
                $award_info[$item['activity_id']] = $item;
            },$list);
            $data['list'] = $award_info;
            $redis->set($key,$data);
        }



        return $data['list'];
    }

    /**
     * 获取用户最近一次中奖记录状态
     * @param $user_id
     * @return mixed
     */
    static function getUserAward($user_id)
    {
        $key = "db:award:dialog:user_id:{$user_id}";
        $redis = new RedisService();
        $data  = $redis->get($key);
        if(!$data)
        {
            $sql = "SELECT activity_id,is_read,goods_id FROM lb_win_award_list WHERE user_id = {$user_id} ORDER BY id DESC LIMIT 1";

            $result = Db::query($sql);

            $data['info'] = $result[0] ?? [];

            $redis->set($key,$data,3600);
        }


        return $data['info'];
    }

    /**
     * 更新状态已读
     * @param $activity_id
     * @return int
     *
     */
    static function updateUserAward($activity_id)
    {
        $sql = "update  lb_win_award_list set is_read = 1 WHERE activity_id = {$activity_id} limit 1";

        return Db::execute($sql);
    }

    /**
     * 中奖者信息
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function award_info($activity_id){

        $field = "award.user_id,award.award_time,award.award_num,award.nick_name,award.avatar,award.fight_coupon_num,award.activity_time,address.id as address_id";
        return Db::table($this::AWARD_LIST)->alias("award")->field($field)
            ->where('award.activity_id',intval($activity_id))
            ->join('lb_win_address address','award.activity_id = address.activity_id','LEFT')
            ->find();
    }
}