<?php
/**
 * 周年庆扎气球
 * Date: 2019/7/1
 * Time: 17:40
 */
namespace app\anniversary\controller;
use think\cache\driver\Redis;
class Prickballoon extends Common
{
    #########################redisKEY###############################################
    const KEY = "ANNIVERSARY-PRICKBALLOON-";

    #########################优惠券ID###############################################
    const COUPON_PERCENTAFE60 = 170;//60%几率优惠券
    const COUPON_PERCENTAFE20 = 172;//20%几率优惠券
    const COUPON_PERCENTAFE10 = 174;//10%几率优惠券
    const COUPON_PERCENTAFE8 = 175;//8%几率优惠券
    const COUPON_PERCENTAFE2 = 177;//2%几率优惠券

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
     * 服务器当前时间
     */
    public function now_time()
    {
        $result = array(
            'result' => 1,
            'data' => array(
                'time' => time(),
            ),
        );
        $this->interlayer($result);
    }

    /**
     * 场次判断
     * @return int|string
     */
    private function enter_window()
    {
        $now_time = time();
        $wee_hour = strtotime(date('Y-m-d'));
        //每日五场对应时间
        $round = array(
            3600 * 9 + $wee_hour, 3600 * 12 + $wee_hour, 3600 * 15 + $wee_hour, 3600 * 18 + $wee_hour, 3600 * 21 + $wee_hour
        );
        $round_num = 0;
        foreach ($round as $key => $val) {
            //符合场次时间则返回场次编号
            if ($now_time >= ($val - 600) && $now_time <= ($val + 600)) {
                $round_num = $key + 1;
            }
        }
        return $round_num;
    }

    /**
     * 各场次用户机会校验
     * @param $round_num :  场次编号
     * @param $user_id :  用户id
     * @return int
     */
    private function chance($round_num, $user_id)
    {
        $key = $this::KEY . "DATE-" . date("Y-m-d", time()) . "-ROUND-" . $round_num;
        $chance = $this->handler->sismember($key, $user_id) == 1 ? 0 : 1;
        return $chance;
    }

    /**
     * 用户机会redis写入
     * @param $round_num
     * @param $user_id
     * @return mixed
     */
    private function rewards_result_write($round_num, $user_id)
    {
        $key = $this::KEY . "DATE-" . date("Y-m-d", time()) . "-ROUND-" . $round_num;
        $this->handler->expire($key, 86400);
        $chance = $this->handler->sadd($key, $user_id);
        return $chance;
    }

    /**
     * 场次校验
     * @param int $type
     * @param int $user_id
     * @return int
     */
    public function round_check($type = 1, $user_id = 0, $level = 0)
    {
        if ($type == 1) {
            $request = $this->request->param();
            $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
            (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        }

        //判断当前是否符合场次时间，如果符合场次时间返回当前场次编号
        $round_num = $this->enter_window();
        //当前不是活动时间
        if (empty($round_num)) {
            $result = array(
                'result' => "-41",
                'data' => ['type' => 2],
            );
            $this->interlayer($result);
        }

        $user_id = empty($request['user_id']) ? $user_id : $request['user_id'];

        //判断该用户是否已参与过当前场次
        $is_play = $this->chance($round_num, $user_id);
        if (empty($is_play)) {
            $result = array(
                'result' => "-43",
                'data' => ['type' => 3],
            );
            $this->interlayer($result);
        }

        if ($type == 1) {
            $window_key = $this::KEY . "DATE-" . date("Y-m-d", time()) . "-WINDOW-" . $round_num;
            $window = $this->handler->sismember($window_key, $request['user_id']) == 1 ? 0 : 1;
            if (empty($window)) {
                $result = array(
                    'result' => "-43",
                    'data' => ['type' => 3],
                );
                $this->interlayer($result);
            }
            $result = array(
                'result' => 1,
                'data' => ['type' => 1],
            );
            $this->interlayer($result);
        } else {
            return $round_num;
        }
    }

    /**
     * 弹窗判断接口
     */
    public function window()
    {
        $request = $this->request->param();
        $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
        (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;

        //判断当前是否符合场次时间，如果符合场次时间返回当前场次编号
        $round_num = $this->enter_window();
        //当前不是活动时间
        if (empty($round_num)) {
            $result = array(
                'result' => "-41",
                'data' => ['type' => 2],
            );
            $this->interlayer($result);
        }

        $key = $this::KEY . "DATE-" . date("Y-m-d", time()) . "-WINDOW-" . $round_num;
        $window = $this->handler->sismember($key, $request['user_id']) == 1 ? 0 : 1;
        if (!empty($window)) {
            $this->handler->expire($key, 86400);
            $this->handler->sadd($key, $request['user_id']);
            $result = array(
                'result' => "1",
                'data' => [],
            );
            $this->interlayer($result);
        } else {
            $result = array(
                'result' => "-43",
                'data' => [],
            );
            $this->interlayer($result);
        }
    }

    /**
     * 领取奖励
     */
    public function get_rewards()
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

        $key = $this::KEY . "GET_COUPON_TIME-USER";
        //从redis中取出扎气球活动中用户已领取红包数
        $num = $this->handler->hget($key,$request['user_id']);

        //用户已领取红包大于等于29次，不再让用户领券
        if (!empty($num) && $num >= 29) {
            $this->interlayer(['result' => '-63', 'data' => []]);
        }

        //场次校验,并返回场次编号
        $round_num = $this->round_check(2, $request['user_id']);

        //随机领取优惠券
        $rand = mt_rand(1, 100);
        switch ($rand) {
            case $rand > 0 && $rand <= 60 :
                $coupon_id = $this::COUPON_PERCENTAFE60;
                break;
            case $rand > 60 && $rand <= 80:
                $coupon_id = $this::COUPON_PERCENTAFE20;
                break;
            case $rand > 80 && $rand <= 90:
                $coupon_id = $this::COUPON_PERCENTAFE10;
                break;
            case $rand > 90 && $rand <= 98:
                $coupon_id = $this::COUPON_PERCENTAFE8;
                break;
            case $rand > 98 && $rand <= 100:
                $coupon_id = $this::COUPON_PERCENTAFE2;
                break;
            default:
                $coupon_id = $this::COUPON_PERCENTAFE60;
                break;
        }

        //调用接口领取优惠券
        $get_coupon = $this->getPlatformCoupon($request['user_id'], $coupon_id);

        //领券失败或接口没有反应
        if (empty($get_coupon['result']) || $get_coupon['result'] != 1) {
            $result = empty($get_coupon) ? ['result' => "-45", 'message' => "啊哦，没有领到~", 'data' => []] : $get_coupon;
            $this->interlayer($result, 1);
        }

        //中奖记录写入
        $this->rewards_result_write($round_num, $request['user_id']);

        //用户领券次数数据写入
        if (empty($num)) {
            //用户第一次领券，写入缓存
            $data = array(
                $request['user_id'] => 1,
            );
            $this->handler->expire($key, 86400 * 60);
            $this->handler->hmset($key, $data);
        } else {
            //用户不是第一次领券，已领次数加一
            $this->handler->Hincrby($key, $request['user_id'], 1);
        }

        $result = array(
            'result' => 1,
            'data' => empty($get_coupon['data'][0]) ? ['id' => $coupon_id] : $get_coupon['data'][0],
        );
        $this->interlayer($result);
    }
}