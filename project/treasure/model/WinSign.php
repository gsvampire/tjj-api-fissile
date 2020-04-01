<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-09-20
 * Time: 15:49
 */
namespace app\treasure\model;
use think\Db;
use think\Model;

class WinSign extends Model
{
    protected $resultSetType = 'collection';
    const TABLE_NAME ="win_sign";

    /**
     * 用户当日签到信息获取
     * @param $user_id
     * @return mixed/一维数组
     */
    public function user_sign($user_id)
    {
        $time = date('Y-m-d');
        $where = array(
            'user_id' => intval($user_id),
            'update_time' => ['gt', $time]
        );
        return db($this::TABLE_NAME)
            ->where($where)
            ->find();
    }
}