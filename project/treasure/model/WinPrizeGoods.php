<?php
namespace app\treasure\model;
use app\treasure\service\RedisService;
use think\Db;

class WinPrizeGoods extends \think\Model
{

    const TABLE_NAME = "lb_win_prize_goods";

    /**
     * 根据商品id 获取 商品信息
     * @param $goods_id  array
     * @return array|mixed
     */
    public static function getAllByIds($goods_id)
    {
        $goods_id = (array) $goods_id;
        if(!$goods_id)
        {
            return [];
        }
        $key = "db:goods:id:".implode("-",$goods_id);
        //缓存
        $redis = new RedisService();
        $goods_info = $redis->get($key);
        if(!$goods_info)
        {
            $whereId = implode(',',$goods_id);
            $sql = "SELECT
                goods_name,
                price,
                img,
                specs,
                id
            FROM
                lb_win_prize_goods 
            WHERE id in ( {$whereId} )";

            $list = Db::query($sql);
            $goods_info = [];
            array_map(function($item) use (&$goods_info){
                $goods_info[$item['id']] = $item;
            },$list);

            $redis->set($key,$goods_info);
        }


        return $goods_info;
    }


}