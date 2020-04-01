<?php
/**
 * 聚宝盆项目好友聚宝盆部分业务
 */

namespace app\v1_2_0\controller;

use think\cache\driver\Redis;
use think\Db;
class Cornucopia extends Common
{
    const MODEL_NAME = 'Cornucopia';

    #########################redis属性设置##########################################
    public $expiration = 300; //默认缓存时间5分钟
    public $expiration_onehour = 3600; //默认缓存时间1小时
    public $expiration_oneday = 86400;//缓存1天时间
    public $expiration_oneweek = 604800;//缓存1周时间
    public $redis; //设置redis对象
    #########################redisKEY###############################################
    const KEY_GOODS = "CORNUCOPIA-EXCHANGE-GOODS-";
    const KEY_WEALTH = "CORNUCOPIA-WEALTH-";
    const KEY_BUBBLE = 'FISSION_CORNUCOPIA_BUBBLE_';
    const KEY_ORDER = "CORNUCOPIA-ORDER-BUBBLE-";

    public function _initialize()
    {
        $request = $this->request->param();
        $this->filter($request);
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }

    /**
     * 兑换商品列表
     * @param int $page
     */
    public function goods_list($page = 1)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $key_goods = $this::KEY_GOODS . "LIST-" . $page;
        if (empty($this->redis->get($key_goods))) {
            $exchange_goods_list = model($this::MODEL_NAME)->exchange_goods_list($page);
            $data['next_page'] = ($exchange_goods_list['count']['count'] / 20 > $page) ? $page + 1 : 0;
            $goods_wealth = array_column($exchange_goods_list['goods_info'], 'financial', 'good_id');
            $java_param['ids'] = implode(array_column($exchange_goods_list['goods_info'], 'good_id'), ',');
            $host = config("DOMAIN_JAVAAPI_TJJ")[4];
            $goods_info = java_api('/goodsList', $java_param, false, $host);
            $exchange_goods_order = model($this::MODEL_NAME)->exchange_goods_order();
            $fire_goods_ids = array_column($exchange_goods_order, 'goods_id');
            foreach ($goods_info['data'] as $key => $val) {
                $data['data'][$key] = array(
                    'goods_id' => $val['goodsId'],
                    'goods_name' => $val['goodsName'],
                    'goods_img' => $val['img640Url'],
                    'stock_num_total' => $val['totalStock'],
                    'goods_wealth' => $goods_wealth[$val['goodsId']],
                    'have_more_spec' => $val['isManySpec'],
                );
                if (!empty($val['spec']) && !empty($val['show'])) {
                    $data['data'][$key]['spec'] = $val['spec'];
                    $data['data'][$key]['show'] = $val['show'];
                }
                $data['data'][$key]['is_fire'] = (in_array($val['goodsId'], $fire_goods_ids)) ? 1 : 0;
            }
            $this->redis->set($key_goods, $data, $this->expiration);
        } else {
            $data = $this->redis->get($key_goods);
        }
        //TODO 给用户财气值增加缓存（hash类型）
        $wealth = model($this::MODEL_NAME)->user_wealth($request['user_id']);
        $data['wealth'] = isset($wealth['total_wealth']) ? $wealth['total_wealth'] : 0;
        $data['result'] = 1;
        $this->interlayer($data);
    }

    /**
     * 好友聚宝盆页面接口
     * @param $gf_user_id
     */
    public function friend_cornucopia($gf_user_id)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $bubble_list = model($this::MODEL_NAME)->bubble_list($gf_user_id);
        $bubble_theif = model($this::MODEL_NAME)->bubble_theif($gf_user_id, $request['user_id']);
        $theif_bubble_ids = array_column($bubble_theif, 'bubble_id');
        $i = 0;
        $time = time();
        foreach ($bubble_list as $key => $val) {
            if ($val['activate_time'] != 0) {
                $data['bubble_list'][$i] = array(
                    'bubble_id' => $val['id'],
                    'wealth' => $val['now_wealth'],
                    'time' => ($val['activate_time'] > $time) ? $val['activate_time'] - $time : 0,
                    'is_steal' => ($val['now_wealth'] > 5 && !in_array($val['id'], $theif_bubble_ids)) ? 1 : 0,
                );
                $i++;
            }
        }
        if (empty($data['bubble_list'])) {
            $data['bubble_list'] = [];
        }
        $user_info = $this->userInfo($request['user_id'] . ',' . $gf_user_id);
        foreach ($user_info as $k => $v) {
            $userInfo[$v['userId']] = array(
                'user_id' => $v['userId'],
                'avatar' => isset($v['avatar']) ? $v['avatar'] : '',
            );
            if (isset($v['nickname'])) {
                $userInfo[$v['userId']]['nickname'] = $v['nickname'];
            } else {
                $userInfo[$v['userId']]['nickname'] = isset($v['username']) ? $v['username'] : '匿名';
            }
        }
        $data['my_info'] = !empty($userInfo[$request['user_id']]) ? $userInfo[$request['user_id']] : [];
        $data['gf_info'] = !empty($userInfo[$gf_user_id]) ? $userInfo[$gf_user_id] : [];
        $gf_wealth = model($this::MODEL_NAME)->user_wealth($gf_user_id);
        $data['gf_wealth'] = !empty($gf_wealth['total_wealth']) ? $gf_wealth['total_wealth'] : 0;
        $key_wealth_list = $this::KEY_BUBBLE . 'NAMEPLATE';;
        $redis_data = $this->redis->get($key_wealth_list);
        if (empty($redis_data)) {
            $gf_wealth_list = model($this::MODEL_NAME)->gf_nameplate();
            $this->redis->set($key_wealth_list, $gf_wealth_list, $this->expiration_onehour);
        } else {
            $gf_wealth_list = $redis_data;
        }
        foreach ($gf_wealth_list as $key => $val) {
            if ($val['wealth'] <= $data['gf_wealth']) {
                $data['wealth_list'][0] = $val;
                $data['wealth_list'][0]['is_light'] = 1;
                break;
            } else if ($val['wealth'] > $data['gf_wealth'] && $key == 0) {
                $data['wealth_list'][0] = $val;
                $data['wealth_list'][0]['is_light'] = 0;
                break;
            }
        }
        $steal_wealth = model($this::MODEL_NAME)->steal_wealth($request['user_id'], $gf_user_id);
        $data['my_steal'] = !empty($steal_wealth['my_steal']['theif_wealth']) ? (int)$steal_wealth['my_steal']['theif_wealth'] : 0;
        $data['gf_steal'] = !empty($steal_wealth['gf_steal']['theif_wealth']) ? $steal_wealth['gf_steal']['theif_wealth'] : 0;
        $result['result'] = 1;
        $result['data'] = $data;
        $this->interlayer($result);
    }

    /**
     * 偷财气接口
     * @param $bubble_id
     * @param $gf_user_id
     */
    public function get_wealth($bubble_id, $gf_user_id)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $key_lock = $this::KEY_BUBBLE . 'GETBUBBLE_' . $bubble_id;
        $bubble_lock = $this->redis->get($key_lock);
        if (!empty($bubble_lock)) {
            $result['result'] = '-7';
            $result['data'] = [];
            $this->interlayer($result);
        }
        $this->redis->set($key_lock, '1', 60);
        $is_theif = model($this::MODEL_NAME)->is_theif($bubble_id, $request['user_id'], $gf_user_id);
        if (!empty($is_theif['id'])) {
            $result['result'] = '-17';
            $result['data'] = [];
            $this->interlayer($result);
        }
        $bubble_info = model($this::MODEL_NAME)->bubble_wealth($bubble_id, $gf_user_id);
        if (!empty($bubble_info)) {
            $bubble_wealth = isset($bubble_info[0]['now_wealth']) ? $bubble_info[0]['now_wealth'] : 0;
            $bubble_theif_wealthe = isset($bubble_info[0]['theif_wealth']) ? $bubble_info[0]['theif_wealth'] : 0;
        } else {
            $result['result'] = '-5';
            $result['data'] = [];
            $this->interlayer($result);
        }
        if ($bubble_wealth > 5) {
            $theif_wealth = mt_rand(2, 5);
        } else {
            $result['result'] = '-3';
            $result['data'] = [];
            $this->interlayer($result);
        }
        $theif_result = model($this::MODEL_NAME)->theif_bubble($request['user_id'], $gf_user_id, $bubble_id, $theif_wealth, $bubble_theif_wealthe);
        if ($theif_result['mysql_error'] == 1) {
            $result['result'] = '-9';
            $result['data'] = [];
            $this->interlayer($result);
        }
        //判断是否可得好友赠送气泡
        //TODO 加缓存
        $gf_send = model($this::MODEL_NAME)->gf_send($request['user_id'], $gf_user_id);
        if ($gf_send['result'] == 1) {
            $i = 0;
            foreach ($gf_send['bubble_info'] as $key => $val) {
                $i = $val['status'] != 2 ? $i + 1 : $i;
            }
            //赠送气泡
            $add_data = array(
                'user_id' => $request['user_id'],
                'gf_user_id' => $gf_user_id,
                'bubble_cate' => 4,
                'status' => $i == 3 ? 0 : 1,
                'activate_time' => $i == 3 ? 0 : time() + 600,
                'get_time' => 0,
                'theif_wealth' => 0,
                'wealth' => mt_rand(1, 2),
                'order_no' => 0,
            );
            model($this::MODEL_NAME)->bubble_add($add_data);
        }
        $this->redis->rm($key_lock);
        $result['result'] = 1;
        $result['get_wealth'] = $theif_wealth;
        $this->interlayer($result);
    }

    /**
     * 创建聚宝盆订单
     * @param $goods_id
     * @param $user_id
     * @param $spec_id
     * @param $address_id
     * @return mixed
     */
    public function set_order($goods_id, $spec_id, $user_id, $address_id)
    {
        $params = array(
            'userId' => $user_id,
            'addressId' => $address_id,
            'goodsId' => $goods_id,
            'specId' => $spec_id,
            'num' => 1,
            'paymentId' => 3,
            'activities' => 'cornucopia',
            'is_post' => 1,
        );
        $host = config("DOMAIN_JAVAAPI_TJJ")[5];
        $res = java_api('order', $params, false, $host);
        return $res;
    }

    /**
     * 兑换商品
     * @param $goods_id
     * @param $spec_id
     * @param $address_id
     */
    public function exchange_goods($goods_id, $spec_id, $address_id)
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        $set_order = $this->set_order($goods_id, $spec_id, $request['user_id'], $address_id);
        if (isset($set_order['status']) && $set_order['status'] == 1) {
            $exchange_result = model($this::MODEL_NAME)->exchange_goods($goods_id, $request['user_id'], $set_order['data']['orderNo']);
            if ($exchange_result['mysql_error'] == 1 || $exchange_result['wealth_error'] == 1) {
                $result['result'] = $exchange_result['mysql_error'] == 1 ? '-13' : '-15';
                $result['data'] = [];
                $this->interlayer($result);
            }
        } else {
            $result['result'] = '-11';
            $result['data'] = [];
            $result['realMessage'] = isset($set_order['realMessage']) ? $set_order['realMessage'] : 'api error';
            $this->interlayer($result);
        }
        $result['result'] = 1;
        $result['data'] = [];
        $this->interlayer($result);
    }

    /**
     * 支付成功赠送气泡
     */
    public function orderBubblePay()
    {
        $body = file_get_contents('php://input');
        $order_info = json_decode($body, 1);
        $key_user = $this::KEY_BUBBLE . 'USER';
        $open_time = $this->handler->hget($key_user, $order_info['user_id']);
        if (empty($open_time) || $open_time > $order_info['pay_time']) {
            echo "OK";
            die;
        }
        $date_today = date('Y-m-d', time());
        $key_order = $this::KEY_ORDER . "PAY-" . $date_today;
        $order_today = $this->handler->hget($key_order, $order_info['user_id']);
        if ($order_today >= 5) {
            echo "OK";
            die;
        }
        $order_bubble = model($this::MODEL_NAME)->bubble_info(2, $order_info['user_id'], 5);
        $i = 0;
        $today_time = strtotime(date("Y-m-d"), time());
        foreach ($order_bubble as $key => $val) {
            $i = time($val['create_time']) > $today_time ? $i + 1 : 0;
        }
        if ($i >= 5) {
            echo "OK";
            die;
        }
        $data = array(
            'user_id' => $order_info['user_id'],
            'bubble_cate' => 2,
            'wealth' => 50,
            'order_no' => $order_info['order_no'],
        );
        if (!empty($order_bubble) && count($order_bubble) >= 3) {
            $data['status'] = 0;
            $data['activate_time'] = 0;
        } else {
            $data['status'] = 1;
            $data['activate_time'] = time() + 600;
        }
        $add_result = model($this::MODEL_NAME)->bubble_add($data);
        if (!empty($add_result)) {
            $this->handler->hset($key_order, $order_info['user_id'], $order_today + 1);
            echo "OK";
            die;
        } else {
            echo "false";
            die;
        }
    }

    /**
     * 退款成功回收气泡
     */
    public function orderBubbleReturn()
    {
        $body = file_get_contents('php://input');
        $order_info = json_decode($body, 1);
        $refund_bubble = model($this::MODEL_NAME)->refund_order_bubble($order_info['user_id'], $order_info['order_no']);
        if ($refund_bubble == 1) {
            $user_info = model($this::MODEL_NAME)->user_wealth($order_info['user_id']);
            $total_wealth = !empty($user_info['total_wealth']) ? $user_info['total_wealth'] : 0;
            $wealth = ($total_wealth > 50) ? 50 : $total_wealth;
            $data_bubble = array(
                'user_id' => $order_info['user_id'],
                'bubble_cate' => 5,
                'wealth' => $wealth,
                'order_no' => $order_info['order_no'],
            );
            Db::startTrans();
            $add_bubble = model($this::MODEL_NAME)->bubble_add($data_bubble);
            if (empty($add_bubble)) {
                Db::rollback();
                echo "false";
                die;
            }
            $data_bubble_detail = array(
                'state' => 3,
                'user_id' => $order_info['user_id'],
                'wealth' => $wealth,
                'total_wealth' => $total_wealth - $wealth,
            );
            $add_bubble_detail = model($this::MODEL_NAME)->bubble_detail_add($data_bubble_detail);
            if (empty($add_bubble_detail)) {
                Db::rollback();
                echo "false";
                die;
            } else {
                Db::commit();
                echo "OK";
                die;
            }
        } else {
            echo "OK";
            die;
        }
    }
}