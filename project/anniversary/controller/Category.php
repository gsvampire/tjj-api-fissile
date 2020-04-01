<?php
/**
 * 周年庆分类页
 * Date: 2019/7/1
 * Time: 16:18
 */
namespace app\anniversary\controller;
use think\cache\driver\Redis;
class Category extends Common
{
    const MODEL_NAME = 'Goods';

    #########################redisKEY###############################################
    const KEY = "ANNIVERSARY-CATEGORY-ACTIVITY_ID-";

    #########################分类页顶部优惠券位置映射关系###############################################
    private $coupon_ids = array(
        '15-9' => array(
            '1' => 179,//第一张
            '2' => 180,//第二张
        ),//女神穿搭优惠券
        '16-8' => array(
            '1' => 226,//第一张
            '2' => 227,//第二张
        ),//男神穿搭优惠券
        '17-6' => array(
            '1' => 181,//第一张
            '2' => 182,//第二张
        ),//主妇优选优惠券
        '18-8' => array(
            '1' => 228,//第一张
            '2' => 229,//第二张
        ),//开学快乐优惠券
    );

    ###################################################################################################

    public function _initialize()
    {
        $request = $this->request->param();
        $this->filter($request);
        try{
            $this->redis = new Redis(config('redis'));
            $this->handler = $this->redis->handler();
        }catch(Exception $e){
            $this->apiLog($_REQUEST,$e->getMessage(),$_SERVER);
        }
    }

    /**
     * 通用分类页数据列表
     * @param $coordinate
     */
    public function category($coordinate)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        //无法检测到对应页面位置
        if (empty($this->coupon_ids[$coordinate])) {
            $this->interlayer(['result' => "-49", 'data' => []]);
        }

        //调主流程接口获取优惠券数据
        $coupon_ids = $this->coupon_ids[$coordinate];
        $ids = implode(',', $coupon_ids);
        $coupon_info = $this->getCouponList($ids);

        //接口未返回正常数据
        if (empty($coupon_info)) {
            $result = array(
                'result' => "-51",
                'message' => empty($coupon_info['message']) ? config("message")["-51"] : $coupon_info['message'],
            );
            $this->interlayer($result, 1);
        }

        //数据整合重组
        foreach ($coupon_info as $key => $val) {
            $key = $this::KEY . "1-CATEGORY-COUPON-COORDINATE-" . $val['id'];
            if (!isset($val['count']) || !isset($val['receive_num']) || $val['count'] - $val['receive_num'] <= 0) {
                //券库存数据异常或券无库存
                $val['status'] = 2;
            } else {
                //判断是否用户已分享过
                $val['status'] = $this->handler->sismember($key, $request['user_id']) == 1 ? 3 : 1;
            }
            switch ($val['id']) {
                case $coupon_ids[1] :
                    $coupon_list[0] = $val;
                    break;
                case $coupon_ids[2] :
                    $coupon_list[1] = $val;
                    break;
                default:
                    $coupon_list[0] = $val;
                    break;
            }
        }
        $data = array(
            'coupon_list' => empty($coupon_list) ? [] : $coupon_list,
        );
        $this->interlayer(['result' => 1, 'data' => $data]);
    }

    /**
     * 分享领钱
     * @param $coordinate
     */
    public function share($coordinate, $num)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        $key = $this::KEY . "1-CATEGORY-COUPON-COORDINATE-" . $this->coupon_ids[$coordinate][$num];
        //查看用户是否已分享此优惠券
        if (empty($this->handler->sismember($key, $request['user_id']))) {
            //领券
            $get_coupon = $this->get_coupon($this->coupon_ids[$coordinate][$num], 1, 0, $request['user_id'], $request['uuid'], $request['token']);
            if (empty($get_coupon['result']) || $get_coupon['result'] != 1) {
                //用户已领取状态，补录redis
                if(isset($get_coupon['result'])&&$get_coupon['result'] == '-1011'){
                    $this->handler->expire($key, 86400 * 60);
                    $this->handler->sadd($key, $request['user_id']);
                }
                $result = array(
                    'result' => "-51",
                    'message' => empty($get_coupon['message']) ? config("message")["-51"] : $get_coupon['message'],
                );
                $this->interlayer($result, 1);
            } else {
                $result['result'] = 1;
            }
            $this->handler->expire($key, 86400 * 60);
            $this->handler->sadd($key, $request['user_id']);
        } else {
            $result['result'] = 1;
        }
        $result['data'] = [];
        $this->interlayer($result);
    }

    /**
     * 万券齐发子页面数据
     * @param $activity_id
     * @param $coordinate
     */
    public function coupon_list($activity_id, $coordinate)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        $key = $this::KEY . $activity_id . "-CATEGORY-COUPON_LIST-COORDINATE-" . $coordinate;
        $redis_result = $this->redis->get($key);

        if (empty($redis_result)) {
            //从表中获取数据
            try{
                $coupon_list = model($this::MODEL_NAME)->goods_list($activity_id, $coordinate);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message);
                $this->returnError(-1, $e->getMessage());
            }

            if (!empty($coupon_list)) {
                //将数组转为以coordinate为key的对象方便前端处理数据
                foreach ($coupon_list as $key => $val) {
                    //补充字段中包含coupon_id时展示
                    if (!empty($val['supplement']) && !empty(json_decode($val['supplement'], true)['shop_coupon_id'])) {
                        //解码补充字段
                        $val = array_merge($val, json_decode($val['supplement'], true));
                        unset($val['supplement']);
                        $coupon[$val['goods_id']] = empty($val) ? [] : $val;
                    }
                }
                $coupon_info = empty($coupon) ? [] : $coupon;
                $this->redis->set($key, $coupon_info, $this::EXPIRATION);
            } else {
                $coupon_info = [];
            }
        } else {
            $coupon_info = $redis_result;
        }

        //整合优惠券列表所需参数
        foreach ($coupon_info as $key => $val) {
            if (!empty($val['shop_coupon_id']) && !empty($val['goods_id'])) {
                $coupon_id = empty($coupon_id) ? $val['shop_coupon_id'] : $coupon_id . ',' . $val['shop_coupon_id'];
                $goods_id = empty($goods_id) ? $val['goods_id'] : $goods_id . ',' . $val['goods_id'];
            }
        }

        if (!empty($coupon_id) && !empty($goods_id)) {
            //参数齐全，请求优惠券列表接口
            $goods_coupon_list = $this->goodsCouponList($coupon_id, $goods_id, $request['user_id'], $request['uuid'], $request['token'], $coordinate);
        } else {
            $this->interlayer(['result' => '-61', 'data' => []]);
        }

        //优惠券列表获取失败
        if (empty($goods_coupon_list['result']) || $goods_coupon_list['result'] != 1) {
            $result = empty($goods_coupon_list) ? ['result' => "-55", 'message' => "列表加载失败~", 'data' => []] : $goods_coupon_list;
            $this->interlayer($result, 1);
        }

        $data = array(
            'goods_info' => $coupon_info,
            'goods_ids' => implode(",", array_keys($coupon_info)),
            'coupon_info' => empty($goods_coupon_list['data']) ? [] : $goods_coupon_list['data'],
        );
        $this->interlayer(['result' => 1, 'data' => $data]);
    }
}