<?php
/**
 * 打卡项目
 */

namespace app\v1_2_0\controller;

use think\cache\driver\Redis;
use think\Db;
class Clock extends Common
{
    const MODEL_NAME = 'Clock';

    private $num = 20;     // 获取的滚动信息的数量

    #########################redis属性设置##########################################
    public $expiration = 300; //默认缓存时间5分钟
    public $expiration_onehour = 3600; //缓存时间1小时
    public $expiration_oneday = 86400;//缓存1天时间
    public $expiration_oneweek = 604800;//缓存1周时间
    public $redis; //设置redis对象
    #########################redisKEY###############################################
    const KEY = "CLOCK-";

    const COUNPON_ID = 121;//打卡成功或失败时优惠券

    const THIRD_COUPON_ID = 136;//第三次打卡时优惠券

    const START_TIME = 1553529600;

    const START_USER_NUM = 6000;

    public function _initialize()
    {
        $request = $this->request->param();
        $this->filter($request);
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }

    /**
     * 主页数据
     */
    public function home()
    {
        $request = $this->request->param();
        if (isset($request['user_id'])) {
            $user_type = $this->user_type($request);
            $result['user_type'] = (!empty($user_type)) ? $user_type : 2;
        } else {
            $result['user_type'] = 4;
        }
        $result['clock_num'] = $this->clock_num(2);
        if ($result['user_type'] == 3) {
            //打卡中用户数据
            $clock_customer_data = $this->clock_customer_home($request['user_id']);
            $result = array_merge($result, $clock_customer_data);
        } else {
            //普通用户（新客，老客）数据
            $general_customer_data = $this->general_customer_home();
            $result = array_merge($result, $general_customer_data);
        }
        $result['result'] = 1;
        $this->interlayer($result);
    }

    /**
     * 用户类型获取接口，1：新客，2：老客，3：参与过打卡活动的老客
     * @param $request
     * @return int
     */
    public function user_type($request)
    {
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $java_param = [
            'user_id' => $request['user_id'],
            'fields' => 'customerType',
        ];
        $host = config("DOMAIN_JAVAAPI_TJJ")[2];
        $goods_info = java_api('user/getInfo', $java_param, false, $host);
        if (empty($goods_info['result']) || $goods_info['result'] != 1 || !isset($goods_info['customerType'])) {
            return 2;
        } elseif ($goods_info['customerType'] == 0) {
            return 1;
        }
        $user_pay = model($this::MODEL_NAME)->user_pay_log($request['user_id']);
        $user_type = empty($user_pay) ? 2 : 3;
        return $user_type;
    }

    /**
     * 打卡人数接口
     * @return int
     */
    public function clock_num($type = 1)
    {
        $key = $this::KEY . "PUNCH-CARD-USER-NUM";
        $key_lock = $this::KEY . "PUNCH-CARD-USER-NUM-LOCK";
        $num = $this->redis->get($key);
        if (empty($num)) {
            $time = ceil((time() - $this::START_TIME) / 5);
            $clock_num = $time * 3 + $this::START_USER_NUM;
            $data = array(
                'clock_num' => $clock_num,
                'time' => time(),
            );
            $this->redis->set($key, $data, 864000);
        } else {
            $lock = $this->redis->get($key_lock);
            if (empty($lock)) {
                if (time() - $num['time'] >= 5) {
                    $this->redis->set($key_lock, 1, $this->expiration);
                    $time = ceil((time() - $num['time']) / 5);
                    $rand = mt_rand(1, 5);
                    $clock_num = $num['clock_num'] + $time * $rand;
                    $data = array(
                        'clock_num' => $clock_num,
                        'time' => time(),
                    );
                    $this->redis->set($key, $data, 864000);
                    $this->redis->rm($key_lock);
                } else {
                    $clock_num = $num['clock_num'];
                }
            } else {
                $clock_num = $num['clock_num'] + 1;
            }
        }
        if ($type == 1) {
            $result = array(
                'result' => 1,
                'clock_num' => $clock_num,
            );
            $this->interlayer($result);
        } else {
            return $clock_num;
        }
    }

    /**
     * 分类列表
     * @return mixed
     */
    public function cate_list()
    {
        $key = $this::KEY . "CATE-LIST";
        $redis_data = $this->redis->get($key);
        if (empty($redis_data)) {
            $cate_list = model($this::MODEL_NAME)->cate_list();
            $cate_ids = implode(array_column($cate_list, 'cid'), ',');
            $java_param = array(
                'ids' => $cate_ids,
            );
            $host = config("DOMAIN_JAVAAPI_TJJ")[7];
            $cate_info = java_api('category/name', $java_param, false, $host);
            $result = (!isset($cate_info['status']) || $cate_info['status'] != 1) ? '' : $cate_info['data'];
            $this->redis->set($key, $result, $this->expiration);
        } else {
            $result = $redis_data;
        }
        return $result;
    }

    /**
     * 商品列表
     * @param int $cate_id
     * @param int $sort_type
     * @param int $page
     * @param int $type
     * @return mixed
     */
    public function goods_list($cate_id = 0, $sort_type = 1, $page = 1, $type = 1)
    {
        $redis_key = $this::KEY . "GOODS-LIST:CATE_ID-" . $cate_id . "-SORT_TYPE-" . $sort_type . "-PAGE-" . $page;
        $redis_data = $this->redis->get($redis_key);
        if (empty($redis_data)) {
            $goods_list = model($this::MODEL_NAME)->goods_list($cate_id, $sort_type, $page);
            $goods_count = model($this::MODEL_NAME)->goods_count($cate_id);
            $java_param['ids'] = implode(array_column($goods_list, 'goods_id'), ',');
            $host = config("DOMAIN_JAVAAPI_TJJ")[7];
            $goods_info = java_api('goodsList', $java_param, false, $host);
            if (empty($goods_info['data'])) {
                $result['data'] = [];
            } else {
                $goods_names = array_column($goods_list, 'goods_name', 'goods_id');
                $day_nums = array_column($goods_list, 'dayNum', 'goods_id');
                $user_nums = array_column($goods_list, 'userNum', 'goods_id');
                foreach ($goods_info['data'] as $key => $val) {
                    $data[$key] = $val;
                    $data[$key]['goodsName'] = empty($goods_names[$val['goodsId']]) ? $val['goodsName'] : $goods_names[$val['goodsId']];
                    $data[$key]['dayNum'] = empty($day_nums[$val['goodsId']]) ? 0 : $day_nums[$val['goodsId']];
                    $data[$key]['userNum'] = empty($user_nums[$val['goodsId']]) ? 0 : $user_nums[$val['goodsId']];
                }
                $result['data'] = $data;
            }
            $result['next_page'] = ($goods_count / 20 > $page) ? $page + 1 : 0;
            $this->redis->set($redis_key, $result, $this->expiration);
        } else {
            $result = $redis_data;
        }
        if ($type == 1) {
            $result['result'] = 1;
            $this->interlayer($result);
        } else {
            return $result;
        }
    }

    /**
     * 新客、老客首页数据
     * @return mixed
     */
    public function general_customer_home()
    {
        $cate_list = $this->cate_list();
        $cate_index = [
            0 => [
                'id' => 0,
                'name' => "推荐",
            ],
        ];
        $data['cate_list'] = empty($cate_list) ? $cate_index : array_merge($cate_index, $cate_list);
        $goods_list = $this->goods_list(0, 1, 1, 0);
        $data['goods_list'] = $goods_list['data'];
        $data['next_page'] = $goods_list['next_page'];
        return $data;
    }

    /**
     * 打卡中用户首页数据
     * @param $user_id
     * @return mixed
     */
    public function clock_customer_home($user_id)
    {
        $user_info['pay_info'] = model($this::MODEL_NAME)->pay_info($user_id);
        $user_info['clock_info'] = model($this::MODEL_NAME)->clock_info($user_id);
        $data['clock_day'] = count($user_info['clock_info']);
        $data['surplus_day'] = ($user_info['pay_info']['day_num'] > $data['clock_day']) ? $user_info['pay_info']['day_num'] - $data['clock_day'] : 0;
        $data['goods_info'] = json_decode($user_info['pay_info']['goods_info'], true);
        $data['refund_time'] = $user_info['pay_info']['refund_time'];
        if ($user_info['pay_info']['status'] == 1) {
            if (strtotime(date("Y-m-d"), time()) - $user_info['clock_info'][0]['create_time'] > 259200) {
                $data['clock_status'] = 3;
                $update_data = array(
                    'status' => 3
                );
                model($this::MODEL_NAME)->pay_info_update($user_id, $update_data);
                $data['is_clock'] = 0;
                $data['is_popup'] = 0;
            } else {
                $data['clock_status'] = 1;
                $user_info['inviter_info'] = model($this::MODEL_NAME)->inviter_info($user_id);
                $redis_key = $this::KEY . "WINDOWS-POPUP-" . date("Y-m-d", time());
                if ($this->handler->sismember($redis_key, $user_id) == 1) {
                    $data['is_popup'] = 0;
                } else {
                    if (!empty($user_info['inviter_info'])) {
                        $data['is_popup'] = 1;
                        $this->handler->sadd($redis_key, $user_id);
                        $this->handler->expire($redis_key, 86400);
                    } else {
                        $data['is_popup'] = 0;
                    }
                }
                if ($user_info['clock_info'][0]['create_time'] <= strtotime(date("Y-m-d"), time())) {
                    $data['is_clock'] = 1;
                } elseif (isset($user_info['clock_info'][1]['create_time']) && $user_info['clock_info'][1]['create_time'] >= strtotime(date("Y-m-d"), time())) {
                    $data['is_clock'] = 0;
                } else {
                    $data['is_clock'] = empty($user_info['inviter_info']) ? 0 : 1;
                }
            }
        } else {
            $data['clock_status'] = $user_info['pay_info']['status'];
            $data['is_clock'] = 0;
            $data['is_popup'] = 0;
        }
        return $data;
    }

    /**
     * 领取礼物
     */
    public function get_gift()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $key = $this::KEY . "GIFT-GET";
        if ($this->handler->sismember($key, $request['user_id']) == 1) {
            $result['result'] = "-2";
            $result['data'] = [];
            $this->interlayer($result);
        }
        $coupon_id = $this::COUNPON_ID;
        $user_gift = model($this::MODEL_NAME)->user_gift($request['user_id'], $coupon_id);
        if ($user_gift > 1) {
            $result['result'] = "-2";
            $result['data'] = [];
            $this->interlayer($result);
        }
        Db::startTrans();
        $get_gitf = model($this::MODEL_NAME)->get_gift($coupon_id, $request['user_id']);
        if (empty($get_gitf)) {
            $result['result'] = "-3";
            $result['data'] = [];
            $this->interlayer($result);
        }
        $params = array(
            'user_id' => $request['user_id'],
            'token' => $request['token'],
            'uuid' => $request['uuid'],
            'stringCoupon' => "fullCoupon~" . $coupon_id,
            'type' => 1,
        );
        $get_result = api('wap/Coupon/receivePlatformCoupon', $params);
        if ($get_result['result'] != 1) {
            Db::rollback();
            $this->interlayer($get_result, 1);
        }
        $this->handler->sadd($key, $request['user_id']);
        Db::commit();
        $params_coupon = array(
            'user_id' => $request['user_id'],
            'token' => $request['token'],
            'uuid' => $request['uuid'],
            'couponId' => $coupon_id,
        );
        $coupon_info = api('wap/Coupon/getPlatformCoupon', $params_coupon);
        $result = array(
            'result' => 1,
            'coupon_name' => empty($coupon_info['data']) ? "全场通用优惠券" : $coupon_info['data']['name'],
            'coupon_amount' => empty($coupon_info['data']) ? "15" : $coupon_info['data']['amount'],
            'coupon_discount' => empty($coupon_info['data']) ? "3" : $coupon_info['data']['discount'],
            'coupon_time' => date("Y-m-d", time() + 604800),//7天
        );
        $this->interlayer($result);
    }

    /**
     * 领取优惠券（用户第三次打卡时）
     * @param $user_id
     * @param $uuid
     * @param $token
     * @param $coupon_id
     * @return bool
     */
    public function get_coupon($user_id, $uuid, $token, $coupon_id)
    {
        $key = $this::KEY . "COUPON-GET";
        if ($this->handler->sismember($key, $user_id) == 1) {
            //已有领取记录，直接忽略
            return false;
        }
        $user_gift = model($this::MODEL_NAME)->user_gift($user_id, $coupon_id);
        if ($user_gift > 1) {
            //已有领取记录
            return false;
        }
        Db::startTrans();
        $get_gitf = model($this::MODEL_NAME)->get_gift($coupon_id, $user_id);
        if (empty($get_gitf)) {
            //领取记录写入失败
            return false;
        }
        $params = array(
            'user_id' => $user_id,
            'token' => $token,
            'uuid' => $uuid,
            'stringCoupon' => "fullCoupon~" . $coupon_id,
            'type' => 1,
        );
        $get_result = api('wap/Coupon/receivePlatformCouponNoCheck', $params);
        if ($get_result['result'] != 1) {
            Db::rollback();
            return false;
        }
        $this->handler->sadd($key, $user_id);
        Db::commit();
        return true;
    }

    /**
     * 优惠券信息
     */
    public function coupon_info()
    {
        $request = $this->request->param();
        $params_coupon = array(
            'user_id' => $request['user_id'],
            'token' => $request['token'],
            'uuid' => $request['uuid'],
            'couponId' => $this::THIRD_COUPON_ID,
        );
        $coupon_info = api('wap/Coupon/getPlatformCoupon', $params_coupon);
        $result = array(
            'result' => 1,
            'coupon_name' => empty($coupon_info['data']) ? "全场通用优惠券" : $coupon_info['data']['name'],
            'coupon_amount' => empty($coupon_info['data']) ? "20" : $coupon_info['data']['amount'],
            'coupon_discount' => empty($coupon_info['data']) ? "3" : $coupon_info['data']['discount'],
            'coupon_time' => date("Y-m-d", time() + 604800),//7天
        );
        $this->interlayer($result);
    }

    /**
     * 打卡接口
     */
    public function clock()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $key = $this::KEY . "DAY-PUNCH-CARD" . date('Y-m-d', time());
        if ($this->handler->sismember($key, $request['user_id'] . '-2') == 1) {
            $result['result'] = "-10";
            $result['data'] = [];
            $this->interlayer($result);
        } else {
            $i = 1;
        }
        $inviter_info = model($this::MODEL_NAME)->inviter_info($request['user_id']);
        if ($this->handler->sismember($key, $request['user_id'] . '-1') == 1) {
            if (empty($inviter_info)) {
                $result['result'] = "-11";
                $result['data'] = [];
                $this->interlayer($result);
            } else {
                $i = 2;
            }
        }
        $pay_info = model($this::MODEL_NAME)->pay_info($request['user_id']);
        $clock_info = model($this::MODEL_NAME)->clock_info($request['user_id']);
        if (isset($clock_info[1]) && $clock_info[1]['create_time'] >= strtotime(date("Y-m-d"), time())) {
            $result['result'] = "-10";
            $result['data'] = [];
            $this->interlayer($result);
        }
        if (isset($clock_info[0]) && $clock_info[0]['create_time'] >= strtotime(date("Y-m-d"), time())) {
            $result['is_clock'] = 0;
            if (empty($inviter_info)) {
                $result['result'] = "-11";
                $result['data'] = [];
                $this->interlayer($result);
            } else {
                $i = 2;
            }
        } else {
            $result['is_clock'] = !empty($inviter_info) ? 1 : 0;
        }
        if (empty($pay_info)) {
            $result['result'] = "-6";
            $result['data'] = [];
            $this->interlayer($result);
        }
        if ($pay_info['day_num'] <= count($clock_info) || $pay_info['status'] == 2) {
            $result['result'] = "-4";
            $result['data'] = [];
            $this->interlayer($result);
        }
        if ($pay_info['status'] == 3) {
            $result['result'] = "-7";
            $result['data'] = [];
            $this->interlayer($result);
        }
        Db::startTrans();
        $clock_result = model($this::MODEL_NAME)->clock_insert($request['user_id']);
        if (empty($clock_result)) {
            Db::rollback();
            $result['result'] = "-5";
            $result['data'] = [];
            $this->interlayer($result);
        }
        if ($pay_info['day_num'] - count($clock_info) == 1) {
            $update_data = array(
                'status' => 2,
            );
            $pay_info_update = model($this::MODEL_NAME)->pay_info_update($request['user_id'], $update_data);
            if (empty($pay_info_update)) {
                Db::rollback();
                $result['result'] = "-9";
                $result['data'] = [];
                $this->interlayer($result);
            }
            $goods_info = json_decode($pay_info['goods_info'], true);
            $withdraw = $this->withdraw($request['user_id'], $goods_info['goods_price']);
            $this->goods_info_update($pay_info['goods_id']);
            if (empty($withdraw)) {
                $result['result'] = "-8";
                $result['data'] = [];
                $this->interlayer($result);
            }
            if ($withdraw['result'] != 1 && $withdraw['result'] != "-2") {
                Db::rollback();
                $this->interlayer($withdraw, 1);
            }
        }
        Db::commit();
        if (count($clock_info) == 2) {
            $get_coupon_result = $this->get_coupon($request['user_id'], $request['uuid'], $request['token'], $this::THIRD_COUPON_ID);
            $result['get_coupon'] = (!empty($get_coupon_result) == 2) ? 1 : 0;
        }
        $result['result'] = 1;
        $this->handler->expire($key, 86400);
        $this->handler->sadd($key, $request['user_id'] . "-" . $i);
        $this->interlayer($result);
    }

    /**
     * 完成打卡时提现接口
     * @param $user_id
     * @param $amount
     * @return mixed
     */
    public function withdraw($user_id, $amount)
    {
        $params = array(
            'user_id' => $user_id,
            'amount' => $amount,
            'is_post' => 1,
        );
        $host = config('DOMAIN_API_TJJ_SERVICE');
        $result = api('BoostBalance/addAvailableBalance', $params, false, $host);
        $data = array(
            'type' => 4,
            'param' => json_encode($params),
            'result' => empty($result['result']) ? 0 : $result['result'],
            'message' => empty($result['message']) ? '' : $result['message'],
        );
        model($this::MODEL_NAME)->order_receive_insert($data);
        return $result;
    }

    /**
     * 打卡完成时增加该商品打卡完成人数
     * @param $goods_id
     * @return mixed
     */
    public function goods_info_update($goods_id)
    {
        $rand = mt_rand(1, 25);
        $data = array(
            'total_sales_volume' => Db::raw('total_sales_volume+' . $rand),
        );
        $result = model($this::MODEL_NAME)->goods_info_update($goods_id, $data);
        return $result;
    }

    /**
     * 商品详情
     * @param $goods_id
     */
    public function goods_detail($goods_id)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $redis_key = $this::KEY . "GOODS-DETAIL:" . $goods_id;
        $redis_data = $this->redis->get($redis_key);
        if (empty($redis_data)) {
            $params_java = array(
                'goodsId' => $goods_id,
                'fields' => "imgfavsId,goodsId,img320Url,title,isCover,isVisible,favs,imageText",
            );
            $params_java_goods = array(
                'goodsId' => $goods_id,
                'fields' => "goodsId,goodsName",
            );
            $params_java_spec = array(
                'goodsId' => $goods_id,
                'fields' => "specId,groupPrice",
            );
            $host = config("DOMAIN_JAVAAPI_TJJ")[6];
            $goods_detail = java_api('getGoods', $params_java_goods, false, $host);
            $goods_spec = java_api('getGoodsSpec', $params_java_spec, false, $host);
            $goods_imgs = java_api('getGoodsImg', $params_java, false, $host);
            if (!isset($goods_imgs['status']) || !isset($goods_spec['status']) || !isset($goods_detail['status'])) {
                $result['result'] = "-12";
                $result['data'] = [];
                $this->interlayer($result);
            }
            if (isset($goods_detail['status']) && $goods_detail['status'] != 1) {
                $this->interlayer($goods_detail, 1);
            }
            if (isset($goods_spec['status']) && $goods_spec['status'] != 1) {
                $this->interlayer($goods_spec, 1);
            }
            if (isset($goods_imgs['status']) && $goods_imgs['status'] != 1) {
                $this->interlayer($goods_imgs, 1);
            }
            foreach ($goods_spec['specs'] as $key => $val) {
                if (isset($goods_price)) {
                    $goods_price = $goods_price > $val['groupPrice'] ? $val['groupPrice'] : $goods_price;
                } else {
                    $goods_price = $val['groupPrice'];
                }
            }
            $i = 1;
            $j = 0;
            foreach ($goods_imgs['imgs'] as $key => $val) {
                if ($val['favs'] == 1 && !$val['isCover']) {
                    $album[$i] = $val['img320Url'];
                    $i++;
                }
                if ($val['isCover'] == 1) {
                    $album[0] = $val['img320Url'];
                }
                if ($val['imageText'] == 1) {
                    $img[$j] = $val['img320Url'];
                    $j++;
                }
            }
            if (empty($album) || empty($img)) {
                $result['result'] = "-13";
                $result['data'] = [];
                $this->interlayer($result);
            }
            $img[$j + 1] = 'http://tjjimg.taojiji.com/app/goods/price_desc.png';
            $album = array_values($album);
            $img = array_values($img);
            $goods_info = model($this::MODEL_NAME)->goods_info($goods_id);
            $data = array(
                'goods_price' => $goods_price,
                'goods_name' => empty($goods_info['goods_name']) ? $goods_detail['goods']['goodsName'] : $goods_info['goods_name'],
                'day_num' => empty($goods_info['punch_days']) ? 0 : $goods_info['punch_days'],
                'album' => $album,
                'image' => $img,
                'servicesExplain' => 'http://wap.taojiji.com/tjj_m/Tjj/Goods/descDetail',
                'payImage' => 'http://tjjstatic.taojiji.com/webtjj/default/images/users/goodsService.png',
            );
            $redis = $this->redis->set($redis_key, $data, $this->expiration);
        } else {
            $data = $redis_data;
        }
        $params_api = array(
            'goods_id' => $goods_id,
            'user_id' => $request['user_id'],
        );
        $host = config('DOMAIN_API_TJJ_SERVICE');
        $bask_info = api('BaskOrder/getBaskOrder', $params_api, false, $host);
        if (isset($bask_info['result']) && $bask_info['result'] == 1) {
            $bask['baskNum'] = $bask_info['baskNum'];
            $bask['baskList'] = $bask_info['baskList'];
        } else {
            $bask = [];
        }
        $result = array(
            'result' => 1,
            'goods_info' => $data,
            'bask_info' => $bask,
        );
        $this->interlayer($result);
    }

    /**
     * 用户订单数据接收接口（下单时）
     */
    public function pay()
    {
        //2019.5.9订单数据重推处理
        $body = file_get_contents('php://input');
        $request = json_decode($body, 1);
//        $request = $this->request->param();naw

        if (!isset($request['user_id']) || !isset($request['order_no']) || !isset($request['goods_id'])) {
//            $data_receive = array(
//                'param' => json_encode($request),
//                'result' => 0,
//                'type' => 1,
//                'message' => "参数缺失",
//            );
            echo "参数缺失";
            die;
//            $result = array(
//                'result' => "-1",
//                'message' => "参数缺失",
//            );

        } else {
            $pay_info = model($this::MODEL_NAME)->pay_info($request['user_id']);
            if (empty($pay_info)) {
                $goods_detail = model($this::MODEL_NAME)->goods_info($request['goods_id']);
                $goods_info = array(
                    'goods_id' => $request['goods_id'],
                    'goods_name' => $request['goods_name'],
                    'goods_img' => $request['goods_img'],
                    'goods_price' => $request['goods_price'],
                );
                $goods_info['sale_num'] = isset($goods_detail['sale_num']) ? $goods_detail['sale_num'] : 876;
                $goods_info['goods_name'] = isset($goods_detail['goods_name']) ? $goods_detail['goods_name'] : $goods_info['goods_name'];
                $data = array(
                    'user_id' => $request['user_id'],
                    'goods_id' => $request['goods_id'],
                    'order_no' => $request['order_no'],
                    'status' => 1,
                    'pay_time' => $request['pay_time'],
                    'day_num' => empty($goods_detail['punch_days']) ? 0 : $goods_detail['punch_days'],
                    'goods_info' => json_encode($goods_info),
                );
                $insert_result = model($this::MODEL_NAME)->pay_info_insert($data);
                if (!empty($insert_result)) {
                    $insert_clock = model($this::MODEL_NAME)->clock_insert($request['user_id']);
//                    $data_receive = array(
//                        'param' => json_encode($request),
//                        'result' => empty($insert_clock) ? 0 : 1,
//                        'type' => 1,
//                        'message' => empty($insert_clock) ? "punch_card表写入失败" : "成功",
//                    );
                    if (empty($insert_clock)) {
                        echo "punch_card表写入失败";
                        die;
                    } else {
                        echo "OK";
                        die;
                    }
//                    $result = array(
//                        'result' => empty($insert_clock) ? "-1" : "1",
//                        'message' => empty($insert_clock) ? "punch_card表写入失败" : "成功",
//                    );
                } else {
                    echo "pay_log表写入失败";
                    die;

//                    $data_receive = array(
//                        'param' => json_encode($request),
//                        'result' => 0,
//                        'type' => 1,
//                        'message' => "pay_log表写入失败",
//                    );
//                    $result = array(
//                        'result' => "-1",
//                        'message' => "pay_log表写入失败",
//                    );
                }
            } else {
                echo "不可重复下单";
                die;

//                $data_receive = array(
//                    'param' => json_encode($request),
//                    'result' => 0,
//                    'type' => 1,
//                    'message' => "不可重复下单",
//                );
//                $result = array(
//                    'result' => "-1",
//                    'message' => "不可重复下单",
//                );
            }
        }
//        $receive_insert = model($this::MODEL_NAME)->order_receive_insert($data_receive);
//        if (empty($receive_insert)) {
//            $result = array(
//                'result' => "-1",
//                'message' => "receive表写入失败",
//            );
//        }
//        return json_encode($result);
    }

    /**
     * 邀请数据接收
     */
    public function inviterData()
    {
        $body = file_get_contents('php://input');
        $order_info = json_decode($body, 1);
        $data = array(
            'user_id' => $order_info['b_user_id'],
            'b_user_id' => $order_info['user_id'],
            'order_no' => $order_info['order_no'],
            'pay_time' => $order_info['pay_time'],
        );
        $insert_result = model($this::MODEL_NAME)->inviter_info_insert($data);
        if (!empty($insert_result)) {
            echo "OK";
            die;
        } else {
            echo "false";
            die;
        }
    }

    /**
     * 退款数据接收
     */
    public function refundData()
    {
        $body = file_get_contents('php://input');
        $order_info = json_decode($body, 1);
        $refund_info = model($this::MODEL_NAME)->refund_info($order_info['user_id'], $order_info['order_no']);
        if (empty($refund_info)) {
            echo "OK";
            die;
        } else {
            $data = array(
                'status' => 3,
                'refund_time' => $order_info['refund_time'],
            );
            $update_result = model($this::MODEL_NAME)->pay_info_update($order_info['user_id'], $data);
            if (!empty($update_result)) {
                echo "OK";
                die;
            } else {
                echo "false";
                die;
            }
        }
    }

    /**
     * 顶部滚动条假数据
     */
    public function user_stock_info()
    {
        $res = [];
        $userIconArr = array_rand(range(1, 654), $this->num);
        $nickArr = config("nickname");
        for ($i = 0; $i < $this->num; $i++) {
            $res[] = [
                'user' => $nickArr[$userIconArr[$i]],
                'userIcon' => 'http://' . config('DOMAIN_TJJ_UPLOAD') . '/group/userIcon/1' . $userIconArr[$i] . '.jpg',
                'price' => mt_rand(90, 300) / 10,
            ];
        }
        $result['result'] = 1;
        $result['data'] = $res;
        $this->interlayer($result);
    }

    /**
     * 用户礼物领取资格反查接口
     * @param $user_id
     * @return int
     */
    public function userPunchCheck($user_id)
    {
        $pay_info = model($this::MODEL_NAME)->pay_info($user_id);
        if (empty($pay_info['status'])) {
            $code = 0;
        }
        if ($pay_info['status'] == 2 || $pay_info['status'] == 3) {
            $code = 1;
        } else {
            $code = 0;
        }
        $result = array(
            'result' => 1,
            'message' => '',
            'code' => $code,
        );
        return json_encode($result);
    }

    /**
     * H5下单接口
     * @param $user_id
     * @param $address_id
     * @param $goods_id
     * @param $spec_id
     * @param $payment_id
     * @param $cate_id
     */
    public function create_order($user_id, $address_id, $goods_id, $spec_id, $payment_id, $cate_id = 0)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($user_id, $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $goods_check = model($this::MODEL_NAME)->goods_check($goods_id, $cate_id);
        if (empty($goods_check)) {
            $result['result'] = "-35";
            $result['data'] = [];
            $this->interlayer($result);
        }
        $params = array(
            'userId' => $user_id,
            'addressId' => $address_id,
            'goodsId' => $goods_id,
            'specId' => $spec_id,
            'num' => 1,
            'paymentId' => $payment_id,
            'activities' => 'signInCashBack',
            'version' => empty($request['version']) ? '2.16.0' : $request['version'],
            'is_post' => 1,
        );
        $host = config("DOMAIN_JAVAAPI_TJJ")[5];
        $res = java_api('order', $params, false, $host);
        $this->interlayer($res, 1);
    }

    /**
     * 小程序下单接口
     * @param $user_id  用户id
     * @param $address_id   地址id
     * @param $goods_id   商品id
     * @param $spec_id   规格id
     * @param $num   商品数量
     * @param $payment_id   支付方式ID。对于无需实际支付的订单，请传入固定的值：3
     * @param $cate_id   规格id
     */
    public function wxCreateOrder($userId, $addressId, $goodsId, $specId, $num, $payment_id, $cateId = 0)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($userId, $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $goods_check = model($this::MODEL_NAME)->goods_check($goodsId, $cateId);
        if (empty($goods_check)) {
            $result['result'] = "-35";
            $result['data'] = [];
            $this->interlayer($result);
        }
        $params = array(
            'userId' => $userId,
            'addressId' => $addressId,
            'goodsId' => $goodsId,
            'specId' => $specId,
            'num' => $num,
            'paymentId' => $payment_id,
            'activities' => 'signInCashBack',
            'version' => empty($request['version']) ? '2.16.0' : $request['version'],
            'is_post' => 1,
        );
        $host = config("DOMAIN_JAVAAPI_TJJ")[5];
        $res = java_api('order', $params, false, $host);
        $this->interlayer($res, 1);
    }
}