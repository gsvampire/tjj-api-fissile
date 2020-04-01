<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-09-20
 * Time: 14:08
 */

namespace app\treasure\controller;


use app\treasure\service\IndexService;
use think\Db;
use think\Log;
use think\Request;
use think\cache\driver\Redis;
use app\treasure\service\UserService;
use app\treasure\service\TreasureticketService;


class Signinfo extends Common
{

    const STRING_USER_SIGN_TREASURE_INFO = 'string_user_sign_treasure_info:';

    const STRING_SING_ROUND_ROLL_INFO = 'string_sign_round_roll_info:';

    protected $avatar = 'http://tjjimg.taojiji.com/app/user_center/default_icon.png';//默认用户头像

    const DB_BLACK_INFO = 'common';

    const STRING_USER_ADD_SHARE_INFO = 'string_user_add_share_info:';


    protected $rollList = [
        '1' => '签到',
        '2' => '分享',
        '3' => '集集美食屋',
    ];

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->request = $request;
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }


    //用户今日是否签过到
    protected function isSignInfo($userId)
    {
        try {
            //缓存层判断
            $redisInfo = $this->handler->get(self::STRING_USER_SIGN_TREASURE_INFO . date('Y-m-d') . $userId);
            if (!empty($redisInfo)) return false;
            //数据库层判断 做一次保险
            $userService = new UserService();
            $modelInfo = $userService->getUserLatelyTime($userId);
            if ($modelInfo === false) return false;
            $days = date('Y-m-d');
            if ($days == $modelInfo) return false;
            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }


    //签到接口
    public function index()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));

            if (empty($userId) || empty($uuid) || empty($token) || $userId < 0)
                return returnJson([], '活动过于火爆，稍后再试吧~', -1);

            //验证用户token
            $checkToken = $this->goCheckToken($userId, $uuid, $token);
            if (empty($checkToken))
                return returnJson([], '登录过期,请重新登录~', -1);

            //验证用户天域值跟黑名单信息
            $blackInfo = $this->userBlackInfo($userId);
            if(empty($blackInfo))
                return returnJson([],'用户数据获取失败，请稍后再试吧~',-1);
            if ($blackInfo['tianyu'] >= 3 || in_array(self::DB_BLACK_INFO, $blackInfo['hintInfo']))
                return returnJson([], '活动过于火爆，升级中~', -1);

            //用户今日是否签到过
            $daySign = $this->isSignInfo($userId);
            if (empty($daySign))
                return returnJson([], '每日只能签到一次哦~', -1);

            //用户最新一次签到时间
            $userService = new UserService();
            $lastSignTime = $userService->userSignDayInfo($userId);
            if ($lastSignTime === false)
                return returnJson([], '数据有延迟,稍后再试吧~', -1);
            $couponNum = 0;   //初始化夺宝券数量-给用户增加夺宝券
            $initSerialDays = 0; //初始化连续 签到天数
            $tomCouponNum = 0;//明日夺宝券
            if (empty($lastSignTime)) {
                $addInfo = $userService->addSignInfo($userId);
                if (empty($addInfo))
                    return returnJson([], '数据异常,稍后再试吧~', -1);
                $couponNum = 2;
                $initSerialDays = 1;
                $tomCouponNum = 3;
                $isValue=0;

            } else {
                $nowDate = date('Y-m-d');
                $diffDays = $this->diffDays($nowDate, $lastSignTime['last_sign_time']);

                //更新签到表
                $savaDays = $userService->saveSerialDays($userId, $diffDays);
                if (empty($savaDays))
                    return returnJson([], '签到失败,请再试一次~', -1);

                //计算夺宝券数量
                $newLastSignTime = $userService->userSignDayInfo($userId);
                $newSerialDays = $newLastSignTime['serial_days'];
                if ($newSerialDays <= 5) {
                    $couponNum = intval($newSerialDays) + 1;
                    $tomCouponNum = intval($newSerialDays) + 2;
                } else {
                    $couponNum = 7;
                    $tomCouponNum = 7;
                }
                $initSerialDays = $newSerialDays;
                $isValue=1;
                $oldLastTime=$lastSignTime['last_sign_time'];
                $oldSerialDays=$lastSignTime['serial_days'];
            }


            //获取用户头像昵称
            $userBaseInfo = $userService->getUserInfo($userId);
            if (empty($userBaseInfo)) {
                $nickName = '';
                $avatar = '';
            } else {
                $nickName = $userBaseInfo['nickName'];
                $avatar = $userBaseInfo['avatar'];
            }

            //增加夺宝券
            $treasure = new TreasureticketService();
            if($initSerialDays==7){
                //第七天给8张优惠券
                $treaInfo = $treasure->get($type = 1, 8, $userId);
            }else{
                $treaInfo = $treasure->get($type = 1, $couponNum, $userId);
            }

            if ($treaInfo['result'] == 1 ||$treaInfo['result'] =='-11004'){
                //删除王老师夺宝券缓存缓存
                IndexService::clearTicketAccount($userId);
                $totalTicket=IndexService::getMyTicket($userId);
                //缓存用户今日签到信息
                $this->handler->setnx(self::STRING_USER_SIGN_TREASURE_INFO . date('Y-m-d') . $userId, time());
                $this->handler->EXPIRE(self::STRING_USER_SIGN_TREASURE_INFO . date('Y-m-d') . $userId, 86400 * 2);

                return returnJson(['total_num'=>$totalTicket,'this_num'=>isset($treaInfo['num'])?$treaInfo['num']:0,'serial_days' => $initSerialDays, 'coupon_num' => $tomCouponNum,
                    'nick_name' => $nickName, 'avatar' => $avatar], '签到成功', 1);
            }
               if(empty($isValue)){
                  $userService->deloldsign($userId);
               }else{
                  $userService->saveoldSign($userId,$oldSerialDays,$oldLastTime);
               }
               $this->handler->del(self::STRING_USER_SIGN_TREASURE_INFO . date('Y-m-d') . $userId);
                return returnJson([], $treaInfo['message'], -1);


        } catch (\Exception $exception) {
            Log::info('该方法发生异常:'.__FUNCTION__.',异常信息为:'.$exception->getMessage());
            return returnJson([], '系统异常', -1);
        }
    }


    //计算两个日期相差的天数
    protected function diffDays($endTime, $startTime)
    {
        $startTime = strtotime($startTime);
        $endTime = strtotime($endTime);
        $days = round(($endTime - $startTime) / 3600 / 24);
        return intval($days);
    }


    //签到信息列表接口
    public function getUserSerialDays()
    {

        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            if (empty($userId) || empty($uuid) || empty($token) || $userId < 0)
                return returnJson([], '活动过于火爆，稍后再试吧~', -1);

            //验证用户token
            $checkToken = $this->goCheckToken($userId, $uuid, $token);
            if (empty($checkToken))
                return returnJson([], '登录过期,请重新登录~', -1);

            //验证用户天域值跟黑名单信息
            $blackInfo = $this->userBlackInfo($userId);
            if(empty($blackInfo))
                return returnJson([],'用户数据获取失败，请稍后再试吧~',-1);
            if ($blackInfo['tianyu'] >= 3 || in_array(self::DB_BLACK_INFO, $blackInfo['hintInfo']))
                return returnJson([], '活动过于火爆，升级中~', -1);

            $isAllowSign = $this->isSignInfo($userId);
            if (empty($isAllowSign)) {
                $allowInfo = '0';//不允许签到
            } else {
                $allowInfo = '1';//允许签到
            }

            $userService = new UserService();
            //数据为空
            $info = $userService->userSignDayInfo($userId);
            if (empty($info))
                return returnJson(['serial_days' => 0, 'coupon_num' => 2, 'allow_sign' => $allowInfo], '请求成功', 1);

            $nowDay = date('Y-m-d');
            $lastSignDay = $info['last_sign_time'];
            $diffDays = $this->diffDays($nowDay, $lastSignDay);
            if ($diffDays > 1)
                return returnJson(['serial_days' => 0, 'coupon_num' => 2, 'allow_sign' => $allowInfo], '请求成功', 1);

            $finDay = empty($info['serial_days']) ? 0 : $info['serial_days'];
//
            if (intval($finDay) <= 5) {
                $couponNum = intval($finDay) + 2;
            } else {
                $couponNum = 7;
            }

            return returnJson(['serial_days' => intval($finDay), 'coupon_num' => $couponNum, 'allow_sign' => $allowInfo], '请求成功', 1);
        } catch (\Exception $exception) {
            Log::info('该方法发生异常:'.__FUNCTION__.',异常信息为:'.$exception->getMessage());
            return returnJson([], '系统异常', -1);
        }

    }


    //滚动数据接口
    public function rollListInfo()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            if (empty($userId) || empty($uuid) || empty($token) || $userId < 0)
                return returnJson([], '活动过于火爆，稍后再试吧~', -1);

            $redisInfo = $this->handler->get(self::STRING_SING_ROUND_ROLL_INFO . date('Y-m-d'));
            if (!empty($redisInfo))
                return returnJson(json_decode($redisInfo, true), '请求成功', 1);

            $userService = new UserService();

            $nickName = $userService->getIcon();
            foreach ($nickName as $k => $v) {
                $randType = mt_rand(1, 3);
                if ($randType == 1) {
                    $randNum = mt_rand(2, 7);
                } else {
                    $randNum = 2;
                }
                $nickName[$k]['info'] = $this->rollList[$randType] . '获得了' . $randNum . '张夺宝券';
            }
            $this->handler->setex(self::STRING_SING_ROUND_ROLL_INFO . date('Y-m-d'), 600+mt_rand(1,5), json_encode($nickName));

            return returnJson($nickName, '请求成功', 1);
        } catch (\Exception $exception) {
            Log::info('该方法发生异常:'.__FUNCTION__.',异常信息为:'.$exception->getMessage());
            return returnJson([], '系统异常', -1);
        }

    }



    //待开奖执行脚本
    public function isWaitopen()
    {

        try {
            $userService = new UserService();
            //查询要开奖的活动信息
            $mergeInfo = $userService->waitInfo();
            if (empty($mergeInfo)) {
                Log::info('夺宝券开奖,在时间:' . date('Y-m-d H:i:s') . '没有要开奖的数据');
                return returnJson([], '夺宝券在该时间没有数据', 1);
            }

            //数据分割为真假数据
            $falseInfo = []; //假数据集合
            $trueInfo = [];  //真数据集合
            foreach ($mergeInfo as $k => $v) {

                if ($v['is_true'] == 1) {
                    $trueInfo[$k]['activity_id'] = $v['id'];
                    $trueInfo[$k]['goods_id'] = $v['goods_id'];
                    $trueInfo[$k]['begin_time'] = $v['begin_time'];
                } else {
                    $falseInfo[$k]['id'] = $v['id'];
                    $falseInfo[$k]['goods_id'] = $v['goods_id'];
                    $falseInfo[$k]['begin_time'] = $v['begin_time'];
                    $falseInfo[$k]['ticket'] = $v['ticket'];
                }
            }

//
            //假数据开奖
            if (!empty($falseInfo)) {
                $falseOpen = $userService->dofalseOpenAward($falseInfo);
                if (empty($falseOpen)) {
                    Log::info('天天夺宝,假开奖失败,时间:' . date('Y-m-d') . ',数据为:' . json_encode($falseInfo));
                } else {
                    Log::info('天天夺宝,假开奖成功,时间:' . date('Y-m-d') . ',数据为:' . json_encode($falseInfo));
                }
            }


            //真开奖数据
            if (!empty($trueInfo)) {
                $trueOpen = $userService->trueOpenInfo($trueInfo);
                if (empty($trueOpen)) {
                    Log::info('天天夺宝,真开奖失败,时间:' . date('Y-m-d') . ',开奖的数据为:' . json_encode($trueInfo));
                } else {
                    Log::info('天天夺宝,真开奖成功,时间:' . date('Y-m-d') . ',开奖的数据为:' . json_encode($trueInfo));
                }
            }

            return returnJson([], 'T执行成功', 1);
        } catch (\Exception $exception) {
            Log::info('该方法发生异常:'.__FUNCTION__.',异常信息为:'.$exception->getMessage());
            return returnJson([], 'T执行异常', 1);
        }

    }

    //活动作废-走假开奖逻辑脚本
    public function isdeleteopen()
    {
        try {
            $userService = new UserService();
            //查询要开奖的活动信息
            $mergeInfo = $userService->dodeleteInfo();
            if (empty($mergeInfo)) {
                Log::info('夺宝券开奖,在时间:' . date('Y-m-d H:i:s') . '没有要开奖的数据');
                return returnJson([], '夺宝券在该时间没有数据', 1);
            }


            //活动作废走假开奖
            $falseOpen = $userService->dofalseOpenAward($mergeInfo);
            if (empty($falseOpen)) {
                Log::info('天天夺宝,假开奖失败,时间:' . date('Y-m-d') . ',活动ID为:' . json_encode($falseOpen));
            } else {
                Log::info('天天夺宝,假开奖成功,时间:' . date('Y-m-d') . ',数据为:' . json_encode($falseOpen));
            }

            return returnJson([], 'F执行成功', 1);
        } catch (\Exception $exception) {
            Log::info('该方法发生异常:'.__FUNCTION__.',异常信息为:'.$exception->getMessage());
            return returnJson([], 'F执行异常', 1);
        }

    }


    /**
     * @return array
     * 分享增加夺宝券接口
     */
    public function addshareinfo()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            if (empty($userId) || empty($uuid) || empty($token) || $userId < 0)
                return returnJson([], '活动过于火爆，稍后再试吧~', -1);

            $redisInfo = $this->handler->get(self::STRING_USER_ADD_SHARE_INFO . $userId . date('Y-m-d'));
            if (!empty($redisInfo))
                return returnJson([], '该次分享不增加夺宝券', 1);

            //验证用户token
            $checkToken = $this->goCheckToken($userId, $uuid, $token);
            if (empty($checkToken))
                return returnJson([], '登录过期,请重新登录~', -1);

            //验证用户天域值跟黑名单信息
            $blackInfo = $this->userBlackInfo($userId);
            if(empty($blackInfo))
                return returnJson([],'用户数据获取失败，请稍后再试吧~',-1);
            if ($blackInfo['tianyu'] >= 3 || in_array(self::DB_BLACK_INFO, $blackInfo['hintInfo']))
                return returnJson([], '活动过于火爆，升级中', -1);

           //增加夺宝券
            $sTreasure = new TreasureticketService();
            $info = $sTreasure->get($type = 3, $num = 2, $userId);
            if ($info['result'] != 1)
                return returnJson([], $info['message'], -1);
            //删除王老师夺宝券缓存缓存
            IndexService::clearTicketAccount($userId);

            $myticket=IndexService::getMyTicket($userId);
            $this->handler->setex(self::STRING_USER_ADD_SHARE_INFO . $userId . date('Y-m-d'), 86400 + mt_rand(1, 10), time());
            Log::info('天天夺宝,分享增加夺宝券成功,user_id:'.$userId.'，时间:'.date('Y-m-d H:i:s'));
            return returnJson(['ticket'=>$myticket], '已成功分享,获得2张夺宝券~', 1);
        } catch (\Exception $exception) {
            Log::info('该方法发生异常:'.__FUNCTION__.',异常信息为:'.$exception->getMessage());
            return returnJson([], '系统异常', -1);
        }
    }

}
