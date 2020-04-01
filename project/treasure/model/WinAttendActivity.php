<?php
namespace app\treasure\model;
use app\treasure\service\RedisService;
use think\Db;

class WinAttendActivity extends \think\Model
{
    const TABLE_NAME = "lb_win_attend_activity";

    //参与记录
    static function getAttendList($user_id, $page)
    {
        $page_size = config('page_size');
        $offset = ($page - 1) * $page_size;
        //$key = "db:attend:activity:list:{$user_id}:{$page}";
        //$redis = new RedisService();
        /*$data = $redis->get($key);
        if (!$data) {*/
            $list = Db::table(WinAttendActivity::TABLE_NAME)
                ->field('user_id,activity_id,goods_id')
                ->where('user_id' , intval($user_id))
                ->where('is_del' , 0)
                ->group("activity_id")
                ->order("activity_time desc")
                ->limit($offset . ',' . $page_size)
                ->select();

            $sql_count = "SELECT count(DISTINCT activity_id) as num
                FROM lb_win_attend_activity where user_id = {$user_id} and is_del = 0 ";
            $result =  Db::query($sql_count);

            $data['list'] = $list;
            $data['count'] = $result[0]['num'] ?? 0;
         /*   $redis->set($key, $data);
        }*/

        return $data;
    }


    //查询特定人 & 特定活动 参与记录
    static function getAttendRes($user_id, $activity_id)
    {

        $count = Db::table(WinAttendActivity::TABLE_NAME)
            ->where('user_id', intval($user_id))
            ->where('activity_id', intval($activity_id))
            ->count();
        return $count;
    }

    //添加参与记录
    static function addAttendInfo($data)
    {
        $data['activity_time'] = time();
        $data['activity_admin_time'] = time();
        Db::table(self::TABLE_NAME)->insert($data);
        $last_id = Db::table(self::TABLE_NAME)->getLastInsID();
        if ($last_id) {
            $key = "db:add:attend:info:{$data['user_id']}:{$data['activity_id']}";
            $redis = new RedisService();
            $exists = $redis->get($key);
            if ($exists) {
                $redis->incrBy($key);
                self::updateAttendTime($data['user_id'], $data['activity_id']);
            } else {
                $count = self::getAttendRes($data['user_id'], $data['activity_id']);
                if ($redis->incrBy($key, $count) > 1) {
                    self::updateAttendTime($data['user_id'], $data['activity_id']);
                }

            }
        }

        return $last_id;
    }

    //更新参加时间
    static function updateAttendTime($user_id, $activity_id)
    {
        $res = Db::table(self::TABLE_NAME)
            ->where('user_id', intval($user_id))
            ->where('activity_id', intval($activity_id))
            ->update([
                'activity_time' => time(),
            ]);

        return $res;
    }

    /**
     * 活动参与用户列表
     * @param $activity_id
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function use_ticket_list($activity_id)
    {
        return Db::table(WinAttendActivity::TABLE_NAME)
            ->field("CONCAT(user_id,'') as user_id,create_time as date,nick_name as nickname,avatar,ticket_num as num")
            ->where('activity_id', intval($activity_id))
            ->order("create_time desc")->limit(50)
            ->select();
    }

    /**
     * 用户本期活动已投放券数量
     * @param $user_id
     * @param $activity_id
     * @return float|int
     */
    public function used_ticket($user_id, $activity_id)
    {
        return Db::table(WinAttendActivity::TABLE_NAME)
            ->where('user_id', intval($user_id))
            ->where('activity_id', intval($activity_id))
            ->sum("ticket_num");
    }
}