<?php
/**
 * 夺宝券模块
 * Date: 2019/9/20
 * Time: 10:49
 */
namespace app\treasure\controller;
use app\treasure\service\RedisService;
use think\cache\driver\Redis;
use app\treasure\service\TreasureticketService;
use think\Log;
use app\zz\service\GrpcService;
class Treasureticket extends Common
{
    const SERVICE_NAME = "TreasureticketService";
    const KEY = "TREASURE-TREASURETICKET-";

    const DB_BLACK_INFO = 'common';

    const FOOD_TICKET_NUM = 2;//集集美食屋每次获得夺宝券数量

    const NEED_SIGN = 2;//是否需要签到为前置条件，1-需要，2-不需要

    public function _initialize()
    {
        $request = $this->request->param();
        $this->filter($request);
        try {
            $this->redis = new Redis(config('redis'));
            $this->handler = $this->redis->handler();
        } catch (Exception $e) {
            $this->apiLog($_REQUEST, $e->getMessage(), $_SERVER);
        }
    }

    /**
     * 夺宝券使用
     */
    public function use_ticket($request = [])
    {
        $request = empty($request) ? $this->request->param() : $request;
        //用户验证
        if (empty($request['user_id']) || empty($request['uuid']) || empty($request['token']) || empty($this->goCheckToken($request['user_id'], $request['uuid'], $request['token']))) {
            return ['result' => '-11000', 'message' => config('message')['-11000']];
        }

        //验证用户天域值跟黑名单信息
        $blackInfo = $this->userBlackInfo($request['user_id']);
        if (empty($blackInfo))
            return ['result' => '-11032', 'message' => config('message')['-11032']];
        if ($blackInfo['tianyu'] >= 3 || in_array(self::DB_BLACK_INFO, $blackInfo['hintInfo']))
            return ['result' => '-11031', 'message' => config('message')['-11031']];

        //用户加锁，锁时间为两分钟
        $key = $this::KEY . "USER-LOCK-USER_ID:" . $request['user_id'];
        $redis_service = new RedisService();

        //查询用户锁是否开启
        if (empty($redis_service->lock($key, 60))) {
            $this->interlayer(['result' => '-11036']);
        }

        //活动加锁，锁时间为一分钟
        $key_activity = $this::KEY . "ACTIVITY-LOCK-ACTIVITY_ID:" . $request['activity_id'];
        $redis_service = new RedisService();

        //查询用户锁是否开启
        if (empty($redis_service->lock($key_activity, 60))) {
            $redis_service->del($key);
            $this->use_ticket($request);
        }
//            $this->interlayer(['result' => '-11036']);

        $service = new TreasureticketService();
        $result = $service->ticket_use($request['user_id'], $request['num'], $request['activity_id'], $request['goods_id']);

        $redis_service->del($key);
        $redis_service->del($key_activity);
        $this->interlayer($result);
    }

    /**
     * 通过集集美食屋获取夺宝券
     * @return array
     */
    public function getTreasureTicket()
    {
        try {
            $request = $this->request->param();
            //用户验证
            if (empty($request['user_id']) || empty($request['uuid']) || empty($request['token']) || empty($this->goCheckToken($request['user_id'], $request['uuid'], $request['token']))) {
                return ['result' => '-11000', 'message' => config('message')['-11000']];
            }

            //验证用户天域值跟黑名单信息
            $blackInfo = $this->userBlackInfo($request['user_id']);
            if (empty($blackInfo))
                return ['result' => '-11032', 'message' => config('message')['-11032']];
            if ($blackInfo['tianyu'] >= 3 || in_array(self::DB_BLACK_INFO, $blackInfo['hintInfo']))
                return ['result' => '-11031', 'message' => config('message')['-11031']];

            //获取集集美食屋的参与列表
            $key = $this::KEY . "FOOD-GET_TICKET-DATE:" . date("Y-m-d");
            $redis_result = $this->handler->sismember($key, $request['user_id']) == 1 ? 0 : 1;

            //判断用户是否已经参与过美食屋得券
            if ($redis_result) {
                $service = new TreasureticketService();
                $result = $service->get(2, $this::FOOD_TICKET_NUM, $request['user_id'], $this::NEED_SIGN);
                $this->handler->expire($key, $this::EXPIRATION_ONEDAY);
                $this->handler->sadd($key, $request['user_id']);
            } else {
                $result = array(
                    'result' => '-11030',
                    'message' => config("message")['-11030']
                );
            }
        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[TreasureticketController:getTreasureTicket]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $result = array(
                'result' => '-11029',
                'message' => config("message")['-11029']
            );
        }
        return $result;
    }
}