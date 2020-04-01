<?php
/**
 * 周年庆摇摇乐
 * Date: 2019/7/1
 * Time: 17:40
 */
namespace app\anniversary\controller;
use think\cache\driver\Redis;
use think\Db;
class Slots extends Common
{
    const MODEL_NAME = 'Slots';

    #########################redisKEY###############################################
    const KEY = "ANNIVERSARY-SLOTS-";

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
     * 滚动条假数据
     */
    public function user_stock_info()
    {
        $res = [];
        $userIconArr = array_rand(range(1, 654), $this::NUM);
        $nickArr = config("nickname");
        for ($i = 0; $i < $this::NUM; $i++) {
            $res[] = [
                'user' => $nickArr[$userIconArr[$i]],
                'time' => mt_rand(1, 59),
                'price' => mt_rand(5, 20),
            ];
        }
        $result['result'] = 1;
        $result['data'] = $res;
        $this->interlayer($result);
    }

    /**
     * 摇奖接口
     */
    public function draw()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        //风控
        $res = $this->blackList($request['user_id']);
        if (!empty($res)) {
            $this->apiLog($request, '黑名单', [], 1);
            $this->interlayer(['result' => '-63', 'data' => []]);
        }

        $key = $this::KEY . "CHANCE-DATE-" . date("Y-m-d", time());
        //从redis中取出用户当日摇奖次数和分享次数
        $chance['draw_num'] = $this->handler->hget($key, 'draw' . $request['user_id']);
        $chance['share_chance'] = $this->handler->hget($key, 'share' . $request['user_id']);

        //redis中无数据记录，去数据表里查找
        if (empty($chance['draw_num']) || empty($chance['share_chance'])) {
            try{
                $chance = model($this::MODEL_NAME)->chance($request['user_id']);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message);
                $this->returnError(-1, $e->getMessage());
            }
        }

        //当日摇奖次数大于5则无法继续摇奖
        if (!empty($chance) && $chance['draw_num'] > 5) {
            $result['result'] = "-31";
            $result['data'] = [];
            $this->interlayer($result);
        }

        //当日开奖次数大于等于当日已拥有次数，无法继续抽奖
        if (!empty($chance) && ($chance['draw_num'] - $chance['share_chance']) >= 1) {
            $result['result'] = "-33";
            $result['data'] = [];
            $this->interlayer($result);
        }

        //摇奖记录为空，做今日第一次摇奖处理
        if (empty($chance)) {
            $data = [
                'user_id' => $request['user_id'],
                'date' => date('Y-m-d', time()),
                'draw_num' => 1,
                'share_chance' => 0,
            ];
            Db::startTrans();
            try{
                $insert_result = model($this::MODEL_NAME)->insert_chance($data);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message, $data);
                $this->returnError(-1, $e->getMessage());
            }

            //数据写入失败
            if (empty($insert_result)) {
                Db::rollback();
                $result = [
                    'result' => "-47",
                    'data' => [],
                ];
                $this->interlayer($result);
            }

            $redis_data = [
                'draw' . $request['user_id'] => 1,
                'share' . $request['user_id'] => 0,
            ];
            $this->handler->expire($key, 86400);
            $redis_result = $this->handler->hmset($key, $redis_data);
            //redis写入失败
            if (empty($redis_result)) {
                Db::rollback();
                $result = [
                    'result' => "-59",
                    'data' => [],
                ];
                $this->interlayer($result);
            }

            //数据写入全部成功，提交事务
            Db::commit();
        } else {
            $data = [
                'key' => 'draw_num',
                'num' => 1,
            ];
            Db::startTrans();
            try{
                $update_result = model($this::MODEL_NAME)->update_chance($request['user_id'], $data);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message, $data);
                $this->returnError(-1, $e->getMessage());
            }

            //数据库更新失败
            if (empty($update_result)) {
                Db::rollback();
                $result = [
                    'result' => "-47",
                    'data' => [],
                ];
                $this->interlayer($result);
            }

            $redis_result = $this->handler->Hincrby($key, 'draw' . $request['user_id'], 1);
            //redis更新失败
            if (empty($redis_result) || $redis_result <= $chance['draw_num']) {
                Db::rollback();
                $result = [
                    'result' => "-59",
                    'data' => [],
                ];
                $this->interlayer($result);
            }

            //数据更新全部成功，提交事务
            Db::commit();
        }

        //取出用户本次待领取优惠券id
        $coupon_id = $this->coupon_id($request['user_id']);

        if (!empty($coupon_id['coupon_id']) || !empty($coupon_id['goods_id'])) {
            Db::startTrans();
            if (empty($coupon_id['got_info'])) {
                //用户无领取记录，在表中插入记录
                $sql_data = array(
                    'user_id' => $request['user_id'],
                    'coupon_ids' => $coupon_id['coupon_id']
                );
                try{
                    $sql_result = model($this::MODEL_NAME)->insert_coupon($sql_data);
                }catch(Exception $e) {
                    $message = $e->getMessage();
                    $this->apiLog($request, $message, $sql_data);
                    $this->returnError(-1, $e->getMessage());
                }
            } else {
                //用户有记录，在表中更新
                if (empty($coupon_id['sql_data'])) {
                    //未取到用户表中的记录，从表里获取后再更新字段
                    try{
                        $got_coupon = model($this::MODEL_NAME)->got_coupon($request['user_id']);
                    }catch(Exception $e) {
                        $message = $e->getMessage();
                        $this->apiLog($request, $message);
                        $this->returnError(-1, $e->getMessage());
                    }
                    $coupon_ids = empty($got_coupon['coupon_ids']) ? '' : $got_coupon['coupon_ids'];
                } else {
                    $coupon_ids = $coupon_id['sql_data'];
                }
                $sql_data = array(
                    'coupon_ids' => empty($coupon_ids) ? $coupon_id['coupon_id'] : $coupon_ids . ',' . $coupon_id['coupon_id']
                );
                try{
                    $sql_result = model($this::MODEL_NAME)->update_coupon($request['user_id'], $sql_data);
                }catch(Exception $e) {
                    $message = $e->getMessage();
                    $this->apiLog($request, $message, $sql_data);
                    $this->returnError(-1, $e->getMessage());
                }
            }

            if (empty($sql_result)) {
                $this->interlayer(['result' => '-53', 'data' => ['share_num' => $chance['share_chance']]]);
            }

            $coupon_key = $this::KEY . "USER-GOT_COUPON_ID-USER_ID:" . $request['user_id'];
            //删除redis
            $redis_result = empty($this->redis->get($coupon_key)) ? 1 : $this->redis->rm($coupon_key);
            if (empty($redis_result)) {
                $this->interlayer(['result' => '-53', 'data' => ['share_num' => $chance['share_chance']]]);
            }

            //领券
            $get_coupon = $this->get_coupon($coupon_id['coupon_id'], 2, 0, $request['user_id'], $request['uuid'], $request['token']);
            //领券失败
            if (empty($get_coupon) || $get_coupon['result'] != 1) {
                Db::rollback();
                $result = empty($get_coupon) ? ['result' => "-35", 'message' => "出了点小问题，等会再来试试吧~"] : $get_coupon;
                $result['data']['share_num'] = $chance['share_chance'];
                $this->interlayer($result, 1);
            }

            //redis更新
            $this->redis->set($coupon_key, $sql_data['coupon_ids'], 180);
            Db::commit();
            $data = [
                'coupon_id' => $coupon_id['coupon_id'],
                'coupon_name' => empty($get_coupon['data']['couponName']) ? "无门槛优惠券" : $get_coupon['data']['couponName'],
                'coupon_price' => empty($get_coupon['data']['couponAmount']) ? "10" : $get_coupon['data']['couponAmount'],
                'goods_id' => $coupon_id['goods_id'],
                'share_num' => $chance['share_chance']
            ];
            $result = array(
                'result' => 1,
                'data' => $data,
            );
        } else {
            $result = array(
                'result' => "-53",
                'data' => ['share_num' => $chance['share_chance']],
            );
        }
        $this->interlayer($result);
    }

    /**
     * 摇摇乐用户中奖优惠券id获取
     * @param $user_id
     * @return int
     */
    private function coupon_id($user_id)
    {
        //查询后台配置的摇摇乐优惠券
        try{
            $coupon_ids = model("Goods")->goods_list(1, "12-1");
        }catch(Exception $e) {
            $message = $e->getMessage();
            $this->apiLog($_REQUEST, $message);
            $this->returnError(-1, $e->getMessage());
        }
        if (empty($coupon_ids)) {
            //后台没有配置摇摇乐优惠券
            return 0;
        }

        $i = 0;
        foreach ($coupon_ids as $k => $v) {
            if (!empty($v['supplement']) && !empty(json_decode($v['supplement'], 1)['shop_coupon_id'])) {
                $coupon_id[$i] = json_decode($v['supplement'], 1)['shop_coupon_id'];
                $goods_ids[$coupon_id[$i]] = empty($v['goods_id']) ? 0 : $v['goods_id'];
                $i++;
            }
        }
        if (empty($coupon_id)) {
            //后台设置数据有误，shop_coupon_id未定义
            return 0;
        }

        $key = $this::KEY . "USER-GOT_COUPON_ID-USER_ID:" . $user_id;

        //取出用户已领取的优惠券id
        $redis_result = $this->redis->get($key);
        if (empty($redis_result)) {
            //redis中无数据，读取数据表
            try{
                $got_coupon = model($this::MODEL_NAME)->got_coupon($user_id);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($_REQUEST, $message);
                $this->returnError(-1, $e->getMessage());
            }

            //记录表中取出的数据，与redis数据做区分
            $sql_result = empty($got_coupon['coupon_ids']) ? '' : $got_coupon['coupon_ids'];
        } else {
            $got_coupon = ['coupon_ids' => $redis_result];
        }

        if (!empty($got_coupon)) {
            $got_coupon_ids = explode(',', $got_coupon['coupon_ids']);
            $new_coupon = array_diff($coupon_id, $got_coupon_ids);

            if (!empty($new_coupon)) {
                //随机返回用户未领取过的优惠券id
                $new_coupon_id = $new_coupon[array_rand($new_coupon)];
                $result = [
                    'coupon_id' => $new_coupon_id,
                    'goods_id' => $goods_ids[$new_coupon_id],
                    'sql_data' => empty($sql_result) ? '' : $sql_result,
                    'got_info' => $got_coupon
                ];
            } else {
                //无差集，用户已领取后台设置的所有优惠券
                return ['coupon_id' => 0, 'goods_id' => 0];
            }
        } else {
            //用户没有领取过优惠券
            $new_coupon_id = $coupon_id[array_rand($coupon_id)];
            $result = [
                'coupon_id' => $new_coupon_id,
                'goods_id' => $goods_ids[$new_coupon_id],
            ];
        }
        return $result;

    }

    /**
     * 摇摇乐分享
     */
    public function share()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        $key = $this::KEY . "CHANCE-DATE-" . date("Y-m-d", time());
        //从redis中取出用户当日摇奖次数和分享次数
        $chance['draw_num'] = $this->handler->hget($key, 'draw' . $request['user_id']);
        $chance['share_chance'] = $this->handler->hget($key, 'share' . $request['user_id']);

        //redis中无数据，从表中取出
        if (empty($chance['draw_num']) || empty($chance['share_chance'])) {
            try{
                $chance = model($this::MODEL_NAME)->chance($request['user_id']);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message);
                $this->returnError(-1, $e->getMessage());
            }
        }

        //用户今日无摇奖记录或用户当前有摇奖机会，本次分享不加机会
        if (empty($chance) || ($chance['draw_num'] - $chance['share_chance']) < 1) {
            $result = array(
                'result' => "-37",
                'data' => [],
            );
            $this->interlayer($result);
        }

        //今日分享次数小于5次，为用户增加一次摇奖机会
        if (isset($chance['share_chance']) && $chance['share_chance'] < 5) {
            $data = [
                'key' => 'share_chance',
                'num' => 1,
            ];
            Db::startTrans();
            try{
                $update_result = model($this::MODEL_NAME)->update_chance($request['user_id'], $data);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message, $data);
                $this->returnError(-1, $e->getMessage());
            }

            //数据库更新失败
            if (empty($update_result)) {
                Db::rollback();
                $result = [
                    'result' => "-47",
                    'data' => [],
                ];
                $this->interlayer($result);
            }
            $redis_result = $this->handler->Hincrby($key, 'share' . $request['user_id'], 1);
        } else {
            $result = array(
                'result' => "-57",
                'data' => [],
            );
            $this->interlayer($result);
        }

        if (empty($redis_result) || $redis_result <= $chance['share_chance']) {
            //redis更新失败
            Db::rollback();
            $result = array(
                'result' => "-39",
                'data' => [],
            );
        } else {
            //数据更新成功，提交事务
            Db::commit();
            $result = array(
                'result' => 1,
                'data' => [],
            );
        }
        $this->interlayer($result);
    }

    /**
     * 当前抽奖机会
     */
    public function chance()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        $key = $this::KEY . "CHANCE-DATE-" . date("Y-m-d", time());
        //从redis中取出用户当日摇奖次数和分享次数
        $chance['draw_num'] = $this->handler->hget($key, 'draw' . $request['user_id']);
        $chance['share_chance'] = $this->handler->hget($key, 'share' . $request['user_id']);

        //redis中无数据，从表中取出
        if (empty($chance['draw_num']) || empty($chance['share_chance'])) {
            try{
                $chance = model($this::MODEL_NAME)->chance($request['user_id']);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message);
                $this->returnError(-1, $e->getMessage());
            }
        }

        if (empty($chance)) {
            $data['chance'] = 1;
            $data['status'] = 1;
        } elseif (($chance['draw_num'] - $chance['share_chance']) < 1) {
            $data['chance'] = 1;
            $data['status'] = 1;
        } else {
            $data['chance'] = 0;
            $data['status'] = $chance['share_chance'] < 5 ? 2 : 3;
        }
        $result['result'] = "1";
        $result['data'] = $data;
        $this->interlayer($result);
    }

    /**
     * 摇摇乐弹窗展示校验
     */
    public function window()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        $key = $this::KEY . "DATE-" . date("Y-m-d", time()) . "-WINDOW";
        if (empty($this->handler->sismember($key, $request['user_id']))) {
            $this->handler->expire($key, 86400);
            $this->handler->sadd($key, $request['user_id']);
            $result['result'] = 1;
        } else {
            $result['result'] = '-1';
        }
        $result['data'] = [];
        $this->interlayer($result);
    }

    /**
     * 查看redis
     * @param $type
     * @param $key
     */
    public function redis($type, $key)
    {
        switch ($type) {
            case 1:
                $chance = $this->redis->get($key);
                break;
            case 2:
                $chance = $this->handler->smembers($key);
                break;
            case 3:
                $chance = $this->handler->hgetall($key);
                break;
        }
        var_dump($chance);
        die;
    }

    /**
     * 删除redis
     * @param $type
     * @param $key
     * @param int $num
     */
    public function delete_redis($type, $key, $num = 0)
    {
        switch ($type) {
            case 1:
                $result = $this->redis->rm($key);
                break;
            case 2:
                $result = $this->handle->srem($key, $num);
                break;
            case 3:
                $result = $this->handler->hdel($key);
                break;
        }
        var_dump($result);
        die;
    }
}