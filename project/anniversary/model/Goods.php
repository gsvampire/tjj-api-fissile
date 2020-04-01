<?php
/**
 * 商品数据
 * User: intel
 * Date: 2019/7/17
 * Time: 21:21
 */
namespace app\anniversary\model;
class Goods extends Common
{
    /**
     * 通用商品数据
     * @param $activity_id
     * @param $coordinate
     * @return false|\PDOStatement|string|\think\Collection   二维数组
     */
    public function goods_list($activity_id, $coordinate)
    {
        $goodsModel = $this->dataModel($this::ACTIVITY_GOODS_LIST);
        $field = "info.goods_id,info.supplement";
        $where = array(
            'activity_id' => $activity_id,
            'coordinate' => $coordinate,
        );
        $result = $goodsModel->alias("list")->field($field)->join('lb_activity_goods_info info', 'list.id = info.list_id')->where($where)->order("sort desc,info.id desc")->limit(200)->select();
        return $result;
    }

    /**
     * 通用资源位数据
     * @param $activity_id
     * @param $coordinate
     * @param $type
     * @return false|\PDOStatement|string|\think\Collection 二维数组
     */
    public function link_list($activity_id, $coordinate, $type)
    {
        $linkModel = $this->dataModel($this::ACTIVITY_GOODS_LIST);
        $field = "list.coordinate,info.activity_name,info.jump_link,info.pic_url,info.supplement";
        $where = array(
            'activity_id' => $activity_id,
        );
        $where['coordinate'] = $type == 1 ? $coordinate : ['in', $coordinate];
        $result = $linkModel->alias("list")->field($field)->join('lb_activity_banner_info info', 'list.id = info.list_id')->where($where)->order("sort desc")->limit(200)->select();
        return $result;
    }
}