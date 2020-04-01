<?php

namespace app\zz\model;

use think\Model;

// 精品店铺详情

/**
 * Class ChoiceShopDetail
 * @property int $id
 * @property int $shop_id
 * @property int $sort
 * @property int $list_id
 * @property string $shop_img
 * @package app\zz\model
 */
class ChoiceShopDetail extends Model
{
    public function toSimpleJson()
    {
        return [
            "shop_id" => $this->shop_id,
            "shop_img" => $this->shop_img,
        ];
    }

    /**
     * @param $list_id
     * @return false|\PDOStatement|string|\think\Collection|static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findDetailsByListId($list_id)
    {
        return self::where(['list_id' => $list_id])->order("sort desc")->select();
    }
}