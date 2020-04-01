<?php
/**
 * 幸运号码表
 * Date: 2019/9/22
 * Time: 16:04
 */
namespace app\treasure\model;
use think\Db;
use think\Model;

class WinLuckyNum extends Model
{
    protected $resultSetType = 'collection';
    const TABLE_NAME = "win_lucky_num";

    /**
     * 用户对单个活动已投放的幸运号数量
     * @param $user_id : 用户id
     * @param $activity_id : 活动id
     * @return int|string
     */
    public function lucky_num_count($user_id, $activity_id)
    {
        $where = array(
            'user_id' => $user_id,
            'activity_id' => $activity_id,
            'is_invalid' => 0
        );
        return Db::name('win_lucky_num')->where($where)->count();
    }

    /**
     * @param $activity_id : 活动id
     * @return array : 一维数组
     */
    public function last_lucky_num($activity_id)
    {
        $where = array(
            'activity_id' => $activity_id
        );
        $field = "id,lucky_num,number";
        return Db::name('win_lucky_num')->field($field)->where($where)->order("number desc")->find();
    }

    /**
     * 幸运号码插入
     * @param $data
     * @return mixed ：添加成功的条数
     */
    public function lucky_insert($data)
    {
        return db($this::TABLE_NAME)->insertAll($data);
    }

    /**
     * 幸运号码列表
     * @param $user_id
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function lucky_num_list($user_id,$activity_id){
        $where = array(
            'user_id' => $user_id,
            'activity_id' => $activity_id
        );
        $field = "id,lucky_num,is_invalid";
        return Db::name('win_lucky_num')->field($field)->where($where)->order("id desc")->select();
    }
}