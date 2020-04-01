<?php
namespace app\treasure\model;
use app\treasure\service\RedisService;
use think\Db;

class WinAddress extends Common
{
    const TABLE_NAME = "lb_win_address";
    const AWARD_TABLE_NAME = "lb_win_award_list";

    /**
     * 获取收货地址
     * @param $user_id
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function getAddress($user_id,$activity_id)
    {
        $result = Db::table(self::TABLE_NAME)
            ->field('user_id,activity_id,province,city,district,address,consignee,mobile')
            ->where('user_id',intval($user_id))
            ->where('activity_id',intval($activity_id))
            ->find();
        return $result;
    }

    /**
     * 获取该活动中奖人地址信息
     * @param $activity_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    static function getActivityAddress($activity_id){
        return Db::table(self::AWARD_TABLE_NAME)->alias("award")
            ->field('award.user_id,award.activity_id,address.id as address_id,address.province,address.city,address.district,address.address,address.consignee,address.mobile')
            ->where('award.activity_id',intval($activity_id))
            ->join("lb_win_address address","award.activity_id = address.activity_id","LEFT")
            ->find();
    }

    /**
     * 地址上传
     * @param $data
     * @return mixed ：添加数据的主键
     */
    public function address_upload($data){
        return $this->insert_data_one($this->dataModel($this::ADDRESS), $data);
    }
}