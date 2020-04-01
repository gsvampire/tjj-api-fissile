<?php
namespace app\anniversary\controller;

/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/6/28
 * Time: 18:04
 */
use aliyun\Log;
use think\cache\driver\Redis;
use think\Controller;
use think\Db;
use think\Exception;

class Clear extends Common
{
    const KEY = 'FISSILE_ANNIVERSARY_CLEAR_';
    const COMMONKEY = 'FISSILE_ANNIVERSARY_';

    const CLEAR = 'anniversary_clear_cart';
    const CLICK = 'anniversary_click';
    const SETTING = 'setting';
    const CLEARKEY = 'anniversary_celebrate_refresh_cart';
    const PRE = 'TJJ';

    const COUPONID = 183;//默认优惠券id
    const PERSON = 2000;//默认人数
    const OPENTIME = 1566129600;//中奖时间  时间戳   2019.8.18  20:00(1566129600)
    const JOINNUM = 187684;//弹窗默认显示参与人数
    const LISTEXPIRE = 900;//奖券列表缓存
    const ONEDAY = 86400;

    const SCROLLNUM = 5;
    const HAVENUM = 20;

    public function __construct()
    {
        parent::__construct();
    }


    public function setOpen($time = 1566129600, $expire = 86400)
    {
        $this->redis->set($this::KEY, $time, $expire);
    }

    public function getOpen()
    {
        return $this->redis->get($this::KEY);
    }

    /*
     * 抽奖参与人数  全表扫描不可用！！！
     */
    public function joinNum_abandon()
    {
        $count = Db($this::CLEAR)->field('count(DISTINCT user_id) as count')->find();
        return $count['count'];
    }

    /*
     * 当前用户已获取抽奖券数量
     */
    public function haveNum($user_id)
    {
        $key = $this::KEY . 'HAVENUM:' . $user_id;
        $count = $this->redis->get($key);
        if ($count === '0') {
            return $count;
        }
        if (!$count) {
            try {
                $count = Db($this::CLEAR)->where('user_id=' . $user_id)->count();
            } catch (Exception $e) {
                $count = 0;
                $this->apiLog($_REQUEST, $e->getMessage());
            }
            $this->redis->set($key, $count, $this::LISTEXPIRE);
        }
        return $count;
    }

    /*
     * 开奖设置  人数&优惠券ID
     */
    public function getSetting()
    {
        $key = $this::COMMONKEY . 'CLEAR_GETSETTING';
        $res = $this->redis->get($key);
        if (!$res) {
            $where['set_key'] = $this::CLEARKEY;
            try {
                $res = Db($this::SETTING)->where($where)->column('set_value');
            } catch (Exception $e) {
                $this->apiLog($where, $e->getMessage());
            }
            $this->apiLog($where, '开奖人数设置', $res);

            $person = $this::PERSON;
            $coupon_id = $this::COUPONID;

            if ($res) {
                $res = json_decode($res[0], true);
                $person = isset($res['person_count']) ? $res['person_count'] : $person;
                $coupon_id = isset($res['coupon_id']) ? $res['coupon_id'] : $coupon_id;
            }

            //非去重后数据  无参考价值
            //$joinNum = $this->totalCount();
            //$person = $person>$joinNum?$joinNum:$person;

            //后台配置多个只取第一个
            if (stripos($coupon_id, ',')) {
                $coupon_id = explode(',', $coupon_id);
                $coupon_id = $coupon_id[0];
            }

            $res = [
                'person' => $person,
                'coupon_id' => $coupon_id,
            ];
            $this->redis->set($key, $res, $this->listExpire());
        }

        return $res;
    }

    /*
     * 开奖倒计时
     * @return int 倒计时时间差 秒
     */
    public function countDown()
    {
        //for test
        //$openTime = $this->getOpen();
        //$countDown=$openTime-time();
        $countDown = $this::OPENTIME - time();
        return $countDown > 0 ? $countDown : 0;
    }

    /*
     * 优惠券列表缓存时间
     */
    public function listExpire()
    {
        $countDown = $this->countDown();
        $expire = $countDown < $this::LISTEXPIRE && $countDown > 0 ? $countDown : $this::LISTEXPIRE;
        return $expire;
    }

    /*
     * 弹窗
     * @return isPop int 弹框类型(0:不弹 1:领取抽奖券 2:抽奖券+1)
     */
    public function isPop($user_id)
    {
        $key = $this::KEY;
        $count = $this->haveNum($user_id);
        $key .= $count ? 'SECONDPOP:' : 'FIRSTPOP:';
        $key .= $user_id;
        $pop = $this->redis->get($key);
        $isPop = $pop ? 0 : 1;
        if ($isPop) {
            //没弹过
            $isPop = $count ? 2 : 1;
            $this->redis->set($key, 1, $this->countDown());
        }
        return $isPop;
    }

    /*
     * 抽奖券列表
     * 15min缓存
     */
    public function clearList($user_id)
    {
        //for test
        //$openTime = $this->getOpen();
        //$data['openTime'] = date('Y-m-d',$openTime);
        $data['openTime'] = date('Y-m-d', $this::OPENTIME);

        $key = $this::KEY . 'COUPONLIST:' . $user_id;
        $couponList = $this->redis->get($key);
        if ($couponList === []) {
            $data['couponList'] = [];
            return $data;
        }
        if (!$couponList) {
            try {
                $couponList = Db($this::CLEAR)->field('id,status,type')->where('user_id=' . $user_id)->select();
            } catch (Exception $e) {
                $couponList = [];
                $this->apiLog($_REQUEST, $e->getMessage() . ':CLEARLIST');
            }
            $count = count($couponList);
            for ($i = 0; $i < $count; $i++) {
                $couponList[$i]['id'] = $this->mergeCouponId($couponList[$i]['id']);
                if ($couponList[$i]['status']) {
                    //已开奖，中奖置顶
                    $arr = $couponList[$i];
                    unset($couponList[$i]);
                    array_unshift($couponList, $arr);
                }
            }
            $this->redis->set($key, $couponList, $this->listExpire());
        }
        $data['couponList'] = $couponList;
        return $data;
    }

    /*
     * 拼接抽奖券号码
     */
    public function mergeCouponId($coupon_id)
    {
        $len = strlen($coupon_id);
        $coupon_id = ($len > 8) ? $this::PRE . $coupon_id : $this::PRE . str_repeat('0', 8 - $len) . $coupon_id;
        return $coupon_id;
    }

    /*
     * 分离抽奖券号码
     */
    public function splitCouponId($coupon_id)
    {
        $coupon_id = substr($coupon_id, strlen($this::PRE));
        return (int)$coupon_id;
    }

    /*******************************************主页面*********************************************/

    /*
     * 按钮显示 0:默认显示 1:未中奖 2:立即清空购物车 3:已领取
     */
    public function button($user_id)
    {
        $key = $this::KEY . 'BUTTON:' . $user_id;
        $button = $this->redis->get($key);
        if (!$button) {
            try {
                $state = Db($this::CLEAR)->field('coupon_id')->where('user_id=' . $user_id . ' and status=1')->find();
            } catch (Exception $e) {
                $this->apiLog($_REQUEST, $e->getMessage() . ':CLEAR-BUTTON');
            }
            $button = $state ? 2 : 1;
            if ($state['coupon_id']) {
                $button = 3;
            }
            $this->redis->set($key, $button, $this->listExpire());
        }
        return (int)$button;
    }

    /*
     * 清空购物车页面
     */
    public function clearPage($user_id, $token, $uuid)
    {
        //用户验证
        $this->checkUser($user_id, $token, $uuid);

        $countDown = $this->countDown();//倒计时
        $isPop = 0;//弹框
        $popNum = 0;//参与人数
        $button = 0;//按钮
        if ($countDown > 0) {
            $key = $this::KEY . 'POPNUM';
            $popNum = $this->redis->get($key);
            $this->redis->set($key, ++$popNum, $this->countDown());
            $isPop = $this->isPop($user_id);
        } else {
            $button = $this->button($user_id);
        }
        if ($isPop) {
            $popNum = $popNum > $this::JOINNUM ? $popNum : $this::JOINNUM;
            $popNum = $popNum > 999999 ? '999999+' : $popNum;
        }

        //抽奖券列表
        $couponList = $this->clearList($user_id);
        $data = [
            'countDown' => $countDown,
            'isPop' => $isPop,
            'popNum' => $popNum,
            'button' => $button,
            'openTime' => $couponList['openTime'],
            'couponList' => $couponList['couponList'],
        ];
        $this->returnSuccess(1, $data);
    }


    /*
     * 抽奖券列表
     */
    public function couponList($user_id, $token, $uuid)
    {
        $this->checkUser($user_id, $token, $uuid);
        $couponList = $this->clearList($user_id);
        $couponList['countDown'] = $this->countDown();
        $this->returnSuccess(1, $couponList);
    }

    /*
     * 立即清空购物车
     */
    public function clearCart($user_id, $token, $uuid)
    {
        $this->checkUser($user_id, $token, $uuid);
        $this->isBlack($user_id);
        $countDown = $this->countDown();
        if ($countDown) {
            $this->returnError(-44);
        }
        $button = $this->button($user_id);
        if ($button == 1) {
            $this->returnError(-36);
        }
        if ($button == 3) {
            $this->returnError(-38);
        }
        $data = $this->getSetting();
        $coupon_id = $data['coupon_id'];

        //修改表数据
        $updateData = [
            'coupon_id' => $coupon_id,
            'coupon_time' => time()
        ];

        Db::startTrans();
        try {
            $update = Db($this::CLEAR)->where('user_id=' . $user_id . ' and status=1')->update($updateData);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->apiLog($_REQUEST, $message, $updateData);
            $this->returnError(-51, $e->getMessage());
        }

        $getCoupon = $this->getPlatformCoupon($user_id, $coupon_id);
        if ($getCoupon['result'] == 1) {
            Db::commit();
            $key = $this::KEY . 'BUTTON:' . $user_id;
            $this->redis->set($key, 3, $this->listExpire());
            $this->returnSuccess(1, '领取成功');
        } else {
            Db::rollback();
            $message = isset($getCoupon['message']) ? $getCoupon['message'] : 'Wap/Coupon/receivePlatformCoupon 领取失败';
            $this->returnError(-50, $message);
        }

    }

    /*
     * ta们刚刚中奖了
     */
    public function clearScroll()
    {
        $res = [];
        $userIconArr = array_rand(range(1, 654), $this::SCROLLNUM);
        $nickArr = config("nickname");
        for ($i = 0; $i < $this::SCROLLNUM; $i++) {
            $res[] = [
                'user' => $nickArr[$userIconArr[$i]],
                'userIcon' => 'http://' . config('DOMAIN_TJJ_UPLOAD') . '/group/userIcon/1' . $userIconArr[$i] . '.jpg',
                'time' => mt_rand(1, 59),
                'num' => mt_rand(2, 5),
                'price' => 33,
            ];
        }
        $this->returnSuccess(1, $res);
    }


    /************************************************分享页面************************************************/

    /*
     * 判断当天是否已存在对应关系
     */
    public function checkRelation($user_id, $share_user_id)
    {
        $key = $this::KEY . 'SHARE:';
        $date = date('Y-m-d');
        $has1 = $this->existMem($key . $user_id . $date, $share_user_id);
        $has2 = $this->existMem($key . $share_user_id . $date, $user_id);
        if ($has1 || $has2) {
            return 1;
        } else {
            $time = strtotime($date);

            try {
                $sql = "SELECT (SELECT COUNT(*)  FROM lb_anniversary_clear_cart WHERE user_id = $user_id AND extend= $share_user_id AND create_time > $time LIMIT 1)+(SELECT COUNT(*)  FROM lb_anniversary_clear_cart WHERE user_id = $share_user_id AND extend = $user_id AND create_time > $time LIMIT 1) as count";
                $has = Db()->query($sql);
                $has = $has[0]['count'];

            } catch (Exception $e) {
                $has = 0;
                $this->apiLog($_REQUEST, $e->getMessage() . ':CHECKRELATION', ['sql' => $sql]);
            }

            if ($has) {
                $this->addRelation($user_id, $share_user_id);
            }
            return $has;
        }
    }

    public function addRelation($user_id, $share_user_id)
    {
        $key = $this::KEY . 'SHARE:';
        $date = date('Y-m-d');
        $this->addMem($key . $user_id . $date, $share_user_id, $this::ONEDAY);
        $this->addMem($key . $share_user_id . $date, $user_id, $this::ONEDAY);
    }

    public function shareButton($user_id, $share_user_id)
    {
        $data['countDown'] = $this->countDown();
        if ($user_id == $share_user_id) {
            $data['button'] = 4;
            return $data;
        }
        $data['button'] = 1;
        if ($data['countDown']) {
            $relation = $this->checkRelation($user_id, $share_user_id);
            $data['button'] = $relation ? 3 : 2;
        }
        return $data;
    }

    /*
     * 分享领券
     */
    public function getShare($user_id, $token, $uuid, $share_user_id)
    {
        $this->afterOpen();
        $this->checkUser($user_id, $token, $uuid);
        $this->isBlack($user_id);
        if (!is_numeric($share_user_id) || !$share_user_id) {
            $this->returnError(-46);
        }
        if ($user_id == $share_user_id) {
            $this->returnError(-40);
        }
        $data = $this->shareButton($user_id, $share_user_id);
        if ($data['button'] != 2) {
            $this->returnError(-42, $data['button']);
        }

        $time = time();
        $userHave = $this->haveNum($user_id);
        if ($userHave >= $this::HAVENUM) {
            $this->returnError(-48);
        }

        $data = [
            [
                'user_id' => $user_id,
                'extend' => $share_user_id,
                'type' => 2,
                'create_time' => $time,
            ]
        ];

        $shareHave = $this->haveNum($share_user_id);
        if ($shareHave < $this::HAVENUM) {
            array_push($data, [
                'user_id' => $share_user_id,
                'extend' => $user_id,
                'type' => 1,
                'create_time' => $time,
            ]);
        }

        try {
            $add = Db($this::CLEAR)->insertAll($data);
        } catch (Exception $e) {
            $this->apiLog($_REQUEST, $e->getMessage() . ':GETSHARE', $data);
            $this->returnError(-51, 'getShare失败');
        }

        //写缓存  set集合
        $this->addRelation($user_id, $share_user_id);

        $have = $this::KEY . 'HAVENUM:';
        $list = $this::KEY . 'COUPONLIST:';
        $keys = [
            $have . $user_id,
            $have . $share_user_id,
            $list . $user_id,
            $list . $share_user_id,
        ];
        $this->resetKey($keys);

        $this->returnSuccess(1, '领取成功');
    }


    /*
     * 分享页面
     * button 1:好礼免费领 2:领取抽奖券 3:已领取   4:同一用户
     */
    public function sharePage($user_id, $token, $uuid, $share_user_id)
    {
        //用户验证
        $this->checkUser($user_id, $token, $uuid);

        if (!is_numeric($share_user_id) || !$share_user_id) {
            $this->returnError(-46);
        }

        $data = $this->shareButton($user_id, $share_user_id);
        $this->returnSuccess(1, $data);
    }




    /***********************************************去下单********************************************/

    /*
     * 添加抽奖券记录
     */
    public function addClear($user_id, $order_no)
    {
        $data = [
            'user_id' => $user_id,
            'extend' => $order_no,
            'create_time' => time(),
        ];
        $add = Db($this::CLEAR)->insert($data);
        return $add;
    }

    /*
     * 查询下单抽奖券记录
     */
    public function findClear($user_id)
    {
        $where = [
            'user_id' => $user_id,
            'create_time' => ['gt', strtotime('today')],
            'type' => 0,
        ];
        $count = Db($this::CLEAR)->where($where)->count();
        return $count;
    }

    /*
     * 添加点击记录
     */
    public function addClick($user_id)
    {
        $data = [
            'user_id' => $user_id,
            'create_time' => time(),
        ];
        $add = Db($this::CLICK)->insert($data);
        return $add;
    }

    /*
     * 查询记录
     */
    public function findClick($user_id)
    {
        $where = [
            'user_id' => $user_id,
            'create_time' => ['gt', strtotime('today')]
        ];
        $time = Db($this::CLICK)->where($where)->column('create_time');
        $time = $time ? $time[0] : 0;
        return $time;
    }

    /*
     * 点击记录时间
     */
    public function clickTime($user_id)
    {
        $key = $this::KEY . 'CLICK:' . $user_id . date('Y-m-d');
        $click = $this->redis->get($key);
        if ($click === '0') {
            return $click;
        }

        if (!$click) {
            $click = $this->findClick($user_id);
            $this->redis->set($key, $click, $this->listExpire());
        }
        return $click;
    }

    /*
     * 下单领取
     */
    public function orderCoupon($user_id)
    {
        $key = $this::KEY . 'ORDERCOUPON:' . $user_id . date('Y-m-d');
        $order = $this->redis->get($key);
        if ($order === '0') {
            return $order;
        }
        if (!$order) {
            $order = $this->findClear($user_id);
            $this->redis->set($key, $order, $this->listExpire());
        }
        return $order;
    }

    public function afterOpen()
    {
        $countDown = $this->countDown();
        if (!$countDown) {
            $this->returnSuccess(1, '领券活动已结束');
        }
    }

    /*
     * 点击
     */
    public function click($user_id, $token = '', $uuid = '')
    {
        $this->afterOpen();
        $this->checkUser($user_id, $token, $uuid);
        $this->isBlack($user_id);
        $key = $this::KEY . 'CLICK:' . $user_id . date('Y-m-d');
        try {
            $clickTime = $this->clickTime($user_id);
            if ($clickTime) {
                $this->returnSuccess(1, '已领取任务');
            }
            $this->addClick($user_id);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->apiLog($_REQUEST, $message . ':CLICK');
            $this->returnError(-51, $message);
        }
        $this->redis->set($key, time(), $this->listExpire());
        $this->returnSuccess(1, '领取成功');
    }

    /*
     * 下单领券
     * @param time 下单时间
     */
    public function getOrder()
    {
        $request = $this->commParams();
        $requestParam = $request['order'];
        $user_id = $requestParam['user_id'];
        $time = $requestParam['order_time'];
        $order_id = $requestParam['order_id'];

        $res = $this->intFilter([$user_id, $time, $order_id]);
        if (!$res) {
            $this->apiLog($request, '参数缺失', $requestParam, 1);
            $this->returnData();
        }

        //活动结束
        $countDown = $this->countDown();
        if (!$countDown) {
            $this->apiLog($request, '领券活动已结束', $requestParam, 1);
            $this->returnData();
        }
        //风控
        $res = $this->blackList($user_id);
        if ($res) {
            $this->apiLog($request, '黑名单', $requestParam, 1);
            $this->returnData();
        }

        //是否领取过下单任务
        $clickTime = $this->clickTime($user_id);
        if (!$clickTime || $time < $clickTime) {
            $this->apiLog($request, '未领取下单任务', $requestParam, 1);
            $this->returnData();
        }

        //今天是否下单领过
        $orderCoupon = $this->orderCoupon($user_id);
        if ($orderCoupon) {
            $this->apiLog($request, '今日已领取过下单奖券', $requestParam, 1);
            $this->returnData();
        }

        //20张上限
        $haveNum = $this->haveNum($user_id);
        if ($haveNum >= $this::HAVENUM) {
            $this->apiLog($request, '已达到领取上限', $requestParam, 1);
            $this->returnData();
        }

        //处理数据 添加记录
        try {
            $this->addClear($user_id, $order_id);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $this->apiLog($request, $message);
            echo $message;
            die;
        }

        $key = $this::KEY . 'ORDERCOUPON:' . $user_id . date('Y-m-d');
        $this->redis->set($key, 1, $this->listExpire());
        $this->apiLog($request, '下单领券成功', $requestParam, 1);
        $this->resetKey([$this::KEY . 'COUPONLIST:' . $user_id]);
        $this->returnData();

    }

    public function returnData()
    {
        echo 'OK';
        exit;
    }


    /*
     * 后台配置更新缓存
     */
    public function resetSetting()
    {
        $key = $this::COMMONKEY;
        $keys = [
            $key . 'CLEAR_GETSETTING',
            $key . 'SIGN_COUPON',
        ];
        $this->resetKey($keys);
        $this->returnSuccess(1);
    }


    public function totalCount()
    {
        $total = Db($this::CLEAR)->field('id')->order('id desc')->limit(1)->find();
        return $total['id'];
    }

    //随机的id数组  id已去重
    public function randIds($person)
    {
        //随机范围
        $total = $this->totalCount();
        $forCount = $person + 2000;
        $forCount = $forCount>$total?$total:$forCount;
        $rand = [];
        for ($i = 0; $i < $forCount; $i++) {
            $rand[$i] = mt_rand(1, $total);
        }
        $rand = array_unique($rand);
        $rand = array_values($rand);
        return $rand;
    }


    //查询对应user_id并去重
    public function luckyUser($person)
    {

        $randIds = $this->randIds($person);
        $this->apiLog($randIds, 'rand id');
        $baseCount = 100;
        $luckyUser = [];
        $idCount = ceil(count($randIds) / $baseCount);
        for ($i = 0; $i < $idCount; $i++) {
            $ids = array_slice($randIds, $i * $baseCount, $baseCount);
            $ids = implode(',', $ids);
            try {
                $res = Db($this::CLEAR)->field('id,user_id')->where('id in(' . $ids . ')')->select();
            } catch (Exception $e) {
                $message = $e->getMessage();
                $this->apiLog([], $message, []);
                $this->returnError(-51, $message);
            }
            $this->apiLog(['id' => $ids], '中奖用户id', $res);
            $arr = array_column($res, 'id', 'user_id');//去重 用户id为key,id为value
            $luckyUser = $luckyUser + $arr;
            $luckyCount = count($luckyUser);
            if ($luckyCount >= $person) {
                break;
            }
        }
        $luckyUser = array_slice($luckyUser, 0, $person);
        $ids = implode(',', $luckyUser);
        return $ids;
    }

    /*
     * 是否已开奖
     */
    public function opend()
    {
        $key = $this::KEY . 'OPEND';
        $opend = $this->redis->get($key);
        if ($opend === '0') {
            return $opend;
        }
        if (!$opend) {
            try {
                $opend = Db($this::CLEAR)->where('status=1')->count();

            } catch (Exception $e) {
                $message = $e->getMessage();
                $this->apiLog([], $message, []);
                $this->returnError(-51, $message);
            }
            $this->apiLog([], $opend, []);
            $this->redis->set($key, $opend, $this::listExpire());
        }
        return $opend;
    }

    public function updateLucky()
    {
        $count = $this->countDown();
        if ($count - 7200 > 0) {
            $this->returnSuccess(1, '未到开奖时间');
        }
        $opend = $this->opend();
        if ($opend) {
            $this->returnSuccess(1, '已开奖');
        }
        //中奖人数
        $setInfo = $this->getSetting();
        $person = $setInfo['person'];

        $ids = $this->luckyUser($person);

        try {
            $update = Db($this::CLEAR)->where('id in(' . $ids . ')')->update(['status' => 1, 'lottery_time' => time()]);

        } catch (Exception $e) {
            $message = $e->getMessage() . ':update';
            $this->apiLog(['id' => $ids], $message, []);
            $this->returnError(-51, $message);
        }
        $this->apiLog([], '更新中奖数据', []);

        $key = $this::KEY . 'OPEND';
        $this->redis->set($key, $update, $this::listExpire());
        if ($update == $person) {
            $this->returnSuccess(1, '开奖成功');
        } else {
            //写日志,导表格  todo
            $this->apiLog([], $ids . '开奖人数不足' . $person, []);
            $this->returnSuccess(1, '开奖人数不足');
        }
    }


    public function dmsApi($type = 1)
    {
        $content = [
            [
                "actType" => 2,
                "code" => 500,
                "message" => "test1s"
            ]
        ];
        $log = new Log($type);
        $dms = $log->addDms($content);
        return $dms;
    }


    public function deleteLuckyState($key)
    {
        if($key!='8yhgeh234gv45bv54gaaa'){
            return false;
        }
        $update = [
            'status'=>0,
            'coupon_id'=>0,
            'coupon_time'=>0,
            'lottery_time'=>0,
        ];
        try{
            $update = Db($this::CLEAR)->where('status=1')->update($update);
        }catch (Exception $e) {
            $message = $e->getMessage() . ':update';
            $this->apiLog($update, $message);
            $this->returnError(-1, $message);
        }
        $this->returnSuccess(1);
    }


}