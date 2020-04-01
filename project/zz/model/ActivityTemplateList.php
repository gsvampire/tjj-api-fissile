<?php

namespace app\zz\model;

use think\Model;

// 模板商品表

/**
 * Class ActivityTemplateList
 * @property int $id
 * @property string $type
 * @property string $activity_type
 * @property int $state
 * @property int $start_time
 * @property int $end_time
 * @package app\zz\model
 */
class ActivityTemplateList extends Model
{
    //状态
    const STATE_DOWN = 0; //下架
    const STATE_UP = 1; //上架
    const STATE_DELETED = 2; //删除


    //模板类型
    const TYPE_A = 'A';
    const TYPE_B = 'B';
    const TYPE_C = 'C';

    public static function getSimpleTypes()
    {
        return [self::TYPE_A, self::TYPE_B];
    }

    public function isTypeC()
    {
        return $this->getData("type") == self::TYPE_C;
    }

    public function isTypeA()
    {
        return $this->getData("type") == self::TYPE_A;
    }

    public function toTypeAJson()
    {
        $data = [];
        if ($this->isTypeA()) {
            $detail_models = ActivityTemplateDetail::findDetailsByListModel($this) ?: [];
            $data = modelsReturnJson($detail_models, "toSimpleJson");
        }
        return $data;
    }

    public function toIndexJson()
    {
        $detail_models = ActivityTemplateDetail::findDetailsByListModel($this) ?: [];
        $data = [
            "templates_name" => $this->activity_type,
            "templates_type" => $this->getData("type"),
            "templates_img" => "",
            "templates_link" => "",
        ];
        $goods = [];
        foreach ($detail_models as $index => $detail_model) {
            if ($index == 0) {
                $data["templates_img"] = $detail_model->template_img;
                $data["templates_link"] = $detail_model->jump_value;
            } else {
                $goods[] = $detail_model->toIndexJson();
            }
        }
        $data["goods"] = $goods;
        return $data;
    }

    /**
     * @return static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findCurrentLists()
    {
        $current_section_lists = self::findCurrentSectionLists() ?: [];
        $simple_lists = self::findSimpleLists() ?: [];
        return array_merge($current_section_lists, $simple_lists);
    }

    /**
     * @return false|\PDOStatement|string|\think\Collection|static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findCurrentSectionLists()
    {
        $current_time = time();
        $where = ['start_time' => ['<', $current_time], 'end_time' => ['>', $current_time], 'state' => self::STATE_UP, "type" => self::TYPE_C];
        return self::where($where)->select();
    }

    /**
     * @return false|\PDOStatement|string|\think\Collection|static[]
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findSimpleLists()
    {
        $where = ['state' => self::STATE_UP, "type" => self::TYPE_B];
        return self::where($where)->select();
    }

    /**
     * @return array|false|\PDOStatement|string|Model|static
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function findListByTypeA()
    {
        $where = ['state' => self::STATE_UP, "type" => self::TYPE_A];
        return self::where($where)->find();
    }
}