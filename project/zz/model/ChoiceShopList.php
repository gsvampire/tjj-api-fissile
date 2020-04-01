<?php

namespace app\zz\model;

use think\Model;

// 精品店铺表

/**
 * Class ChoiceShopList
 * @property int $id
 * @property int $start_time
 * @property int $end_time
 * @property int $state
 * @package app\zz\model
 */
class ChoiceShopList extends Model
{
    //状态
    const STATE_DOWN = 0; //下架
    const STATE_UP = 1; //上架
    const STATE_DELETED = 2; //删除

    public function toIndexJson()
    {
        $detail_models = ChoiceShopDetail::findDetailsByListId($this->id);
        return modelsReturnJson($detail_models, "toSimpleJson");
    }

    /**
     * 获取当前时间段的第一个list
     * @return array|false|\PDOStatement|string|Model|static
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findCurrentList()
    {
        $current_time = time();
        $where = ['start_time' => ['<', $current_time], 'end_time' => ['>', $current_time], 'state' => self::STATE_UP];
        return self::where($where)->order("id desc")->find();
    }
}