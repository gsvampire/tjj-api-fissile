<?php
/**
 * 晒单模块
 * Date: 2019/9/26
 * Time: 10:43
 */
namespace app\treasure\model;
use think\Db;

class WinPrizeShare extends Common
{
    /**
     * 获取最新一期晒单内容
     * @param $goods_id
     * @param int $day_num
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function get_bask_info($goods_id, $day_num = 30)
    {
        $where = array(
            'activity.goods_id' => $goods_id,
            'award.award_time' => ['lt', time() - $day_num * 86400]
        );
        $field = "bask.id as bask_id,bask.activity_id,bask.content,bask.img,award.user_id,award.award_time,award.award_num,award.nick_name,award.avatar,award.fight_coupon_num,award.activity_time";
        return $this->dataModel($this::BASK)->alias("bask")->field($field)->
        join('lb_win_prize_activity activity', 'activity.id = bask.activity_id')->
        join('lb_win_award_list award', 'bask.activity_id = award.activity_id')->
        order("award.id desc")->where($where)->find();
    }
}