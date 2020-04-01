<?php

namespace app\zz\model;

use think\Model;

// 模板商品详情

/**
 * Class ActivityTemplateDetail
 * @property int $id
 * @property int $list_id
 * @property string $template_img
 * @property int $jump_type
 * @property string $jump_value
 * @property int $group_type
 * @package app\zz\model
 */
class ActivityTemplateDetail extends Model
{

    const GROUP_TYPE_LEVEL_ZERO = 0; //banner
    const GROUP_TYPE_LEVEL_ONE = 1; //8:00~12:00
    const GROUP_TYPE_LEVEL_TWO = 2; //12:00~16:00
    const GROUP_TYPE_LEVEL_THREE = 3; //16:00~20:00
    const GROUP_TYPE_LEVEL_FOUR = 4; //20:00~8:00

    public function toSimpleJson()
    {
        return [
            "template_img" => $this->template_img,
            "jump_type" => $this->jump_type,
            "jump_value" => $this->jump_value,
        ];
    }

    public function toIndexJson()
    {
        return [
            "goods_img" => $this->template_img,
            "goods_id" => $this->jump_value,
        ];
    }

    public static function getCurrentGroupType()
    {
        $current_hour = date("H");
        if ($current_hour < 8 || $current_hour >= 20) {
            return self::GROUP_TYPE_LEVEL_FOUR;
        } else if ($current_hour < 12) {
            return self::GROUP_TYPE_LEVEL_ONE;
        } else if ($current_hour < 16) {
            return self::GROUP_TYPE_LEVEL_TWO;
        } else if ($current_hour < 20) {
            return self::GROUP_TYPE_LEVEL_THREE;
        }
    }

    /**
     * @param $list_id
     * @return false|\PDOStatement|string|\think\Collection|static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findDetailsByListModel(ActivityTemplateList $list_model)
    {
        $where = ['list_id' => $list_model->id];
        if ($list_model->isTypeC()) {
            $where['group_type'] = ['in', [self::getCurrentGroupType(), self::GROUP_TYPE_LEVEL_ZERO]];
        }
        return ActivityTemplateDetail::where($where)->order("group_type asc,id asc")->select();
    }
}