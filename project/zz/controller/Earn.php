<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-05
 * Time: 17:50
 */

namespace app\zz\controller;

use think\Controller;
use app\zz\model\MRedis;
use app\zz\service\ZActivity;
use think\Request;
use think\response\Json;
use think\Log;
use app\zz\service\GrpcService;
use weixin\WxShare;

class Earn extends Controller
{

    //result -1 普通是否返回  -2 红包失败返回  -3 用户身份是否返回

    const HB_TOTAL_MONEY = 5;
    /**
     * @var Request
     */
    protected $request;

    const HB_DAY_TOTAL_MONEY=50000;

    const HB_LIST_MAX=100;

    /**
     * @var
     */
    protected $redis;

    protected $service;

    public function __construct(Request $request = null)
    {
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
            exit;
        }
        parent::__construct($request);

        $this->request = $request;
        $this->redis = new MRedis();
        $this->service = new ZActivity();
    }

    //验证用户身份
    protected function checkUserToken($userId, $uuid, $token)
    {
        try {

            $arr = [
                'user_id' => $userId,
                'token' => $token,
                'uuid' => $uuid,
                'app_resource' => 0,
            ];
            $grpcService=new GrpcService();
            $mCheckUser = $grpcService::goCheckUserToken($arr);
            return $mCheckUser;

        } catch (\Exception $exception) {
            return false;
        }
    }


    //验证红包信息是否合法
    protected function checkHbValidInfo($hbId)
    {
        try{
            //redis层验证红包信息
            $redisHb = $this->redis->getfivehbuserinfo($hbId);
            if (!empty($redisHb)) return true;
            //数据库层验证红包信息
            $modelHb=$this->service->hbvalidInfo($hbId);
            if(!empty($modelHb)) return true;
            return false;
        }catch (\Exception $exception){
            return false;
        }
    }

    //五元红包悬浮窗接口-正方形那个小弹框-用户是否分享过该红包
    public function suspension()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            $hbId = intval(trim($this->request->param('hb_id')));
            $shareUserId = intval(trim($this->request->param('share_user_id')));

            if (empty($userId) || empty($uuid) || empty($token) || empty($hbId) || empty($shareUserId)||$userId<0||$shareUserId<0||$hbId<0)
                return returnJson([], '参数不全', -1);

            //验证红包信息
            $mHbInfo = $this->checkHbValidInfo( $hbId);
            if (empty($mHbInfo)) return returnJson([], '红包过期或不存在', -2);

            //读取缓存
            $redisShare = $this->redis->getfivehbshare($shareUserId, $hbId);
            //1 分享过 0 未分享
            if (!empty($redisShare)) return returnJson(['share_info' => 1], '用户今日已经分享过红包', 1);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            return returnJson(['share_info' => 0], '用户今日没有分享过红包', 1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }


    //五元红包分享接口  五元红包悬浮窗接口-正方形小弹框-分享完成
    public function sharehb()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            $shareUserId = intval(trim($this->request->param('share_user_id')));
            $hbId = intval(trim($this->request->param('hb_id')));
            if (empty($userId) || empty($uuid) || empty($token) || empty($shareUserId) || empty($hbId)||$userId<0||$shareUserId<0||$hbId<0)
                return returnJson([], '参数不全', -1);

            //验证红包信息
            $mHbInfo = $this->checkHbValidInfo( $hbId);
            if (empty($mHbInfo)) return returnJson([], '红包过期或不存在', -2);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            //写入缓存
            $this->redis->setfivehbshare($shareUserId, $hbId);
            //获取当前红包拆了多钱金额
            $totalMoney = $this->redis->getincrbyfloatmoney($hbId);
            $totalMoney=empty($totalMoney)?0:$totalMoney;

            //写入数据库
            $info = $this->service->addsharehb($shareUserId, $userId, $hbId);
            if (!empty($info)) return returnJson(['total_money' => $totalMoney], '请求成功', 1);
            return returnJson([], '分享失败', -1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }


    //红包详情页面 滚动数据接口
    public function rollinfo()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));

            if (empty($userId) || empty($uuid) || empty($token)||$userId<0)
                return returnJson([], '参数不全', -1);

            $redisInfo = $this->redis->getfivehbrollinfo();
            if (!empty($redisInfo))
                return returnJson(json_decode($redisInfo, true), '请求成功', 1);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            //用户昵称-头像信息
            $nickInfo = $this->service->getIcon();

            foreach ($nickInfo as $k => $v) {
                $nickInfo[$k]['time'] = mt_rand(1, 60);
                $nickInfo[$k]['money'] = mt_rand(100, 500)/100;
            }
            $this->redis->setfivehbrollinfo($nickInfo);
            return returnJson($nickInfo, '请求成功', 1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }


    //给用户生成红包接口
    public function createuserhb()
    {
        return returnJson([], '活动结束', 1);
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            $hbId = intval(trim($this->request->param('hb_id')));

            if (empty($userId) || empty($uuid) || empty($token)||$userId<0)
                return returnJson([], '参数不全', -1);

            //用户今日是否有红包信息
            $totalMoney=0;
            $createInfo = $this->service->userhbinfo($userId);
            if (!empty($createInfo)) {
                //获取当前红包拆了多钱金额
                $totalMoney = $this->redis->getincrbyfloatmoney($createInfo);
                $totalMoney=empty($totalMoney)?0:$totalMoney;
//                $this->redis->setfivehbuserinfo($userId, $createInfo);
                return returnJson(['hb_id' => $createInfo, 'total_moeny' => $totalMoney], '请求成功', 1);
            }


            //读取缓存
//            $redisInfo = $this->redis->getfivehbuserinfo( $hbId);
//            if (!empty($redisInfo)) {
//                $redisHbId = substr($redisInfo, 0, strpos($redisInfo, '-'));
//                return returnJson(['hb_id' => $redisHbId, 'total_moeny' => $totalMoney], '请求成功', 1);
//            }

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);


            if (empty($createInfo) && $createInfo !== false) {

                //用户生成新红包信息
                $addInfo = $this->service->getHbId($userId);
                if (empty($addInfo)) return returnJson([], '系统异常', -1);
                $this->redis->setfivehbuserinfo($userId, $addInfo);
                return returnJson(['hb_id' => $addInfo, 'total_moeny' => $totalMoney], '请求成功', 1);
            }
            return returnJson([], '系统异常', -1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }

    //点击分享的红包图标接口
    public function clickhbicon()
    {
        return returnJson([], '活动结束', 1);
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
//            $shareUserId = intval(trim($this->request->param('share_user_id')));
            $hbId = intval(trim($this->request->param('hb_id')));
            if (empty($userId) || empty($uuid) || empty($token)  || empty($hbId)||$userId<0||$hbId<0)
                return returnJson([], '参数不全', -1);

            //验证红包信息
            $mHbInfo = $this->checkHbValidInfo( $hbId);
            if (empty($mHbInfo)) return returnJson([], '红包过期或不存在', -2);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            //红包所属人信息
            $redisHb = $this->redis->getfivehbuserinfo( $hbId);
            $redisUserId = trim(strrchr($redisHb, '-'), '-');
            //获取当前红包拆了多钱金额
            $totalMoney = $this->redis->getincrbyfloatmoney($hbId);
            $totalMoney = empty($totalMoney) ? 0 : $totalMoney;
            //用户是否拆过该红包
            $userHbInfo = $this->redis->hgetusermoeny($userId, $hbId);
            if(empty($userHbInfo)){
                $helpInfo='no';
                $ownMoney='0.0';
            }else{
                $helpInfo='yes';
                $ownMoney=json_decode($userHbInfo,true)['money'];
            }

            //自己查看或者红包被拆满了 显示列表
            $data = [];
            if ($redisUserId == $userId || $totalMoney >= 5 ) {
//                $redisListInfo = $this->redis->hgetallmoney($hbId);
                $data['total_money'] = $totalMoney;
                $data['help_info'] = $helpInfo;
//                $data['list'] = $redisListInfo;
                $data['own_money'] = $ownMoney;
            } else {
                $data['total_money'] = $totalMoney;
                $data['help_info'] = $helpInfo;
//                $data['list'] = [];
                $data['own_money'] = $ownMoney;
            }
            return returnJson($data, '请求成功', 1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }


    //帮拆红包接口
    public function helpuserhb()
    {

        return returnJson([], '活动结束', 1);
        try{
            $userId = intval(trim($this->request->param('user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            $shareUserId = intval(trim($this->request->param('share_user_id')));
            $hbId = intval(trim($this->request->param('hb_id')));
            $os = trim($this->request->param('os'));
            if (empty($userId) || empty($uuid) || empty($token) || empty($shareUserId) || empty($hbId)||$userId<0||$shareUserId<0||$hbId<0)
                return returnJson([], '参数不全', -1);

            //验证红包信息
            $mHbInfo = $this->checkHbValidInfo( $hbId);
            if (empty($mHbInfo)) return returnJson([], '红包过期或不存在', -2);

            //红包所属人信息
            $redisHb = $this->redis->getfivehbuserinfo( $hbId);
            $redisUserId = trim(strrchr($redisHb, '-'), '-');
            if($redisUserId==$userId) return returnJson([], '自己不能拆自己的红包', -1);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            $randmoney = $this->randMoney($userId, $hbId, $os, $uuid);
            if (empty($randmoney)) return returnJson([], '系统异常', -1);
            $totalMoney = $this->redis->getincrbyfloatmoney($hbId);//累积拆的
            $totalMoney = empty($totalMoney) ? 0 : $totalMoney;
            $ownMoney = $this->redis->hgetusermoeny($userId, $hbId);//自己当前拆的
            $ownMoney = empty($ownMoney) ? 0 : json_decode($ownMoney,true)['money'];

            //帮拆数据入库
            if ($randmoney['type'] == 3) {
                $params = [
                    'user_id' => $userId,
                    'share_user_id' => $shareUserId,
                    'hb_id' => $hbId,
                    'split_money' => $randmoney['money'],
                    'add_time' => time(),
                    'status'=>1,
                ];
                Log::info('帮拆红包接口,用户id:'.$userId.' 拆红包ID: '.$hbId.'拆的信息为:'.
                    json_encode($randmoney).'入库信息为：'.json_encode($params));
                $addId=$this->service->addSplitInfo($params);
                //给用户打款
                $host=config('DOMAIN_PHP_MAIN');
                $url=$host.'/Api2_5_0/activity/giveTransferBalance';
                $params=[
                    'userId'=>$shareUserId,
                    'giveAmount'=>$randmoney['money'],
                    'activityType'=>'15',//五元红包活动标示
                ];
                //给用户打款接口
                $res=httpPost($url,$params,'text');
                $resInfo=json_decode($res,true);
                Log::info('给用户打款,调主流程接口,红包id为:'.$hbId.',请求参数为:'.json_encode($params).'返回信息为:'.$res);
                if($resInfo['result']==1){
                    Log::info('红包id为:'.$hbId.'给用户打款成功,待变更状态,打款信息为:'.json_encode($params));
                    $updateStatus=$this->service->updatestatus($addId);
                    if(!empty($updateStatus)){
                        Log::info('红包id为:'.$hbId.'给用户打款成功,状态变更成功,打款信息为:'.json_encode($params));
                    }else{
                        Log::info('红包id为:'.$hbId.'给用户打款成功,状态变更失败,打款信息为:'.json_encode($params));
                    }
                }

            }

            Log::info('帮拆红包接口,用户id:'.$userId.' 拆红包ID: '.$hbId.'拆的信息为:'.
                json_encode($randmoney).'接口返回信息为：'.json_encode(['total' => $totalMoney, 'own' => $ownMoney, 'type' => $randmoney['type']]));
            return returnJson(['total' => $totalMoney, 'own' => $ownMoney, 'type' => $randmoney['type']],'请求成功',1);

        }catch (\Exception $exception){
            return returnJson([], '系统异常', -1);
        }

    }

//帮拆金额
    protected function randMoney($userId, $hbId, $os, $uuid)
    {
        try {

            //当前红包累积金额
            $historytotal = $this->redis->getincrbyfloatmoney($hbId);
            if ($historytotal >= 5) return ['type' => 1, 'money' => 0.00]; //红包超过5元
            //用户是否拆过该红包
            $checkInfo = $this->redis->getusercheckhb($userId, $hbId);
            if (!empty($checkInfo)) return ['type' => 2, 'money' => 0.00]; //该用户拆过这个红包
            //每日最多只能帮拆3次
            $userCount=$this->redis->getincrusertotalnum($userId);
            if($userCount>=3) return ['type' => 4, 'money' => 0.00]; //每日最多拆3次

            //金额红包累积金额
            $daytotalmoney=$this->redis->getdayincrbyfloatmoney();
//            var_dump($daytotalmoney);exit;
            if($daytotalmoney>=self::HB_DAY_TOTAL_MONEY){
                $money=sprintf("%.2f",0.01);
                //当日累积金额写入
                $this->redis->setdayincrbyfloatmoney($money);
                //当前红包拆包次序写入
                $this->redis->setchecknuninfo($userId, $hbId);
                //用户今日拆红包次数 总共3次
                $this->redis->incrusertotalnum($userId);
                //帮拆系数 写入
                $this->redis->sethelpnuminfo($userId);
                $this->redis->setincrbyfloatmoney($hbId, $money);
                //用户拆红包写入缓存 自己拆的金额
                $this->redis->hsetusermoney($userId, $hbId, ['money'=>$money,'money_time'=>time()]);

                return ['type'=>3,'money'=>$money]; //超过5万后面都是0.01
            }

            //恶意系数-天域值
            $tianyu=$this->badnum($userId);
            if($tianyu==0.1){
                //当前红包拆包次序写入
                $this->redis->setchecknuninfo($userId, $hbId);
                //用户今日拆红包次数 总共3次
                $this->redis->incrusertotalnum($userId);
                //帮拆系数 写入
                $this->redis->sethelpnuminfo($userId);
                $money=sprintf("%.2f",0.01);
                //当日累积金额写入
                $this->redis->setdayincrbyfloatmoney($money);
                //当前红包金额写入
                $this->redis->setincrbyfloatmoney($hbId, $money);
                //用户拆红包写入缓存 自己拆的金额
                $this->redis->hsetusermoney($userId, $hbId, ['money'=>$money,'money_time'=>time()]);

                return ['type'=>3,'money'=>0.01]; //4级恶意系数
            }
            if($tianyu==0.2){
                //当前红包拆包次序写入
                $this->redis->setchecknuninfo($userId, $hbId);
                //用户今日拆红包次数 总共3次
                $this->redis->incrusertotalnum($userId);
                //帮拆系数 写入
                $this->redis->sethelpnuminfo($userId);
                $money=mt_rand(1,5)/100;
                $money=sprintf("%.2f",$money);
                $this->redis->setincrbyfloatmoney($hbId, $money);
                //当日累积金额写入
                $this->redis->setdayincrbyfloatmoney($money);
                //用户拆红包写入缓存 自己拆的金额
                $this->redis->hsetusermoney($userId, $hbId, ['money'=>$money,'money_time'=>time()]);
                return ['type'=>3,'money'=>$money]; //3级恶意系数
            }
            //拆红包系数 购物系数
            $checkeXishu = $this->checknum($userId, $os, $uuid, $hbId);
            //帮拆系数
            $helpXishu = $this->helpnum($userId);
            //邀请系数
            $inviteXishu = $this->invitenum($userId);
            $checkMoney = (mt_rand(2, 5) / 10) * $checkeXishu['check_xishu'] * $checkeXishu['shop_xishu'] * $helpXishu['help_xishu'] * $inviteXishu['invite_xishu'] *$tianyu;
//            $checkMoney = (mt_rand(2, 5) ) * $checkeXishu['check_xishu'] * $checkeXishu['shop_xishu'] * $helpXishu['help_xishu'] * $inviteXishu['invite_xishu'] * 0.8*$tianyu;
//            $checkMoney = number_format($checkMoney, 2, '.', '');
            $checkMoney=sprintf("%.2f",$checkMoney);

            //最大不能超过1元
            if ($checkMoney >= 1) {
                $checkMoney = 1;
            }
            $shengyumoney = self::HB_TOTAL_MONEY - $historytotal;
            if ($shengyumoney <= $checkMoney) {
                //红包金额累计
                $checkMoney = sprintf("%.2f",$shengyumoney);
                $this->redis->setincrbyfloatmoney($hbId, $checkMoney);
                //当日累积金额写入
                $this->redis->setdayincrbyfloatmoney($checkMoney);
                //用户拆红包写入缓存 自己拆的金额
                $this->redis->hsetusermoney($userId, $hbId, ['money'=>$checkMoney,'money_time'=>time()]);
            } else {
                $checkMoney = sprintf("%.2f",$checkMoney);
                //红包金额累计
                $this->redis->setincrbyfloatmoney($hbId, $checkMoney);
                //当日累积金额写入
                $this->redis->setdayincrbyfloatmoney($checkMoney);
                //用户拆红包写入缓存 自己拆的金额
                $this->redis->hsetusermoney($userId, $hbId, ['money'=>$checkMoney,'money_time'=>time()]);
            }
            return ['type' => 3, 'money' => $checkMoney];//正常返回
        } catch (\Exception $exception) {
            return false;
        }
    }

    //拆红包系数
    protected function checknum($userId, $os, $uuid, $hbId)
    {
        $userType = $this->shopnum($userId, $os, $uuid);
        $userType = $userType['shop_num'];
        $oldNum = $this->redis->getchecknuninfo($hbId);
        $oldNum = empty($oldNum) ? 0 : $oldNum;
        $nowNum = $oldNum + 1;
        $xishu = 0.1;
        if ($userType == 1 && $nowNum == 1) {
            $xishu = 1;
        } elseif ($userType == 1 && $nowNum >= 2 && $nowNum < 5) {
            $xishu = 0.8;
        } elseif ($userType == 1 && $nowNum >= 5) {
            $xishu = 0.6;
        } elseif ($userType == 0.8 && $nowNum >= 1 && $nowNum < 4) {
            $xishu = 0.2;
        } else {
            $xishu = 0.1;
        }
        $this->redis->setchecknuninfo($userId, $hbId);
        //用户今日拆红包次数 总共3次
        $this->redis->incrusertotalnum($userId);
        return ['check_xishu' => $xishu, 'shop_xishu' => $userType];
    }

    //购物系数
    protected function shopnum($userId, $os, $uuid)
    {
        //判断新老客 0 老客 1,2新客
        $userStatus = $this->service->userStatus($userId, $os, $uuid);
        if (!empty($userStatus)) return ['shop_num' => 1];
        return ['shop_num' => 0.8];
    }

    //帮拆系数
    protected function helpnum($userId)
    {
        $info = $this->redis->gethelpnuminfo($userId);
        $info = empty($info) ? 0 : $info;
        $xishu = 0.2;
        if ($info < 3) {
            $xishu = 1;
        } elseif ($info >= 3 && $info < 6) {
            $xishu = 0.8;
        } elseif ($info >= 6 && $info < 10) {
            $xishu = 0.6;
        } elseif ($info >= 10 && $info < 20) {
            $xishu = 0.3;
        } else {
            $xishu = 0.2;
        }
        $this->redis->sethelpnuminfo($userId);
        return ['help_xishu' => $xishu];
    }

    //邀请系数
    protected function invitenum($userId)
    {
        $num = $this->service->inviteNum($userId);
        $xishu = 1;
        if ($num < 10) {
            $xishu = 1;
        } elseif ($num >= 10 && $num < 50) {
            $xishu = 1.2;
        } else {
            $xishu = 1.3;
        }
        return ['invite_xishu' => $xishu];
    }

    //恶意系数-天域值
    public function badnum($userId)
    {
        $res=$this->service->badinfo($userId);
        $xishu=0.8;
        if($res===0){
            $xishu=1;
        }elseif ($res===1){
            $xishu=0.5;
        }elseif ($res===2){
            $xishu=0.3;
        }elseif ($xishu===3){
            $xishu=0.2;
        }else{
            $xishu=0.1;
        }
        return $xishu;
    }



    //获取用户头像接口
    public function getusericon()
    {
        try {
            $userId = intval(trim($this->request->param('share_user_id')));
            if (empty($userId)||$userId<0 ) return returnJson([], '参数不全', -1);

            $redis = $this->redis->getusericoninfo($userId);
            if (!empty($redis)) return returnJson(json_decode($redis, true), '', 1);

            $domain = config('API_URL_JAVA_MIDDLE') . '/user/getInfoInBulk?user_ids=';
            $param = $userId . '&fields=nickname,avatar,username';
            $minfo = httpGet($domain . $param);
            $ainfo = json_decode($minfo, true);
            Log::info('请求java接口,获取用户昵称头像信息,请求信息为:'.$domain.$param.'返回信息为:'.$minfo);
            if ($ainfo['result'] != 1){
                return returnJson([], '获取java用户身份异常', -1);
            }
            $userInfo = $ainfo['users'];

            if (empty($userInfo)) {
                return returnJson(['user_id' => $userId, 'nickName' => '', 'avatar' => '']);
            } else {
                $nickName = !isset($userInfo[0]['nickname']) ? substr_replace($userInfo[0]['username'], '****', 3, 4) : $userInfo[0]['nickname'];
                $touxiang = isset($userInfo[0]['avatar']) ? $userInfo[0]['avatar'] : '';
                $data = [
                    'user_id' => $userId,
                    'nickName' => $nickName,
                    'avatar' => $touxiang,
                ];
                $this->redis->setusericoninfo($userId, $data);
                return returnJson(['user_id' => $userId, 'nickName' => $nickName, 'avatar' => $touxiang]);
            }
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }

    //红包列表接口
    public function hblistinfo()
    {
        return returnJson([], '活动结束', 1);
        try {

            $userId = intval(trim($this->request->param('user_id')));
            $hbId = intval(trim($this->request->param('hb_id')));
//            $shareUserId = intval(trim($this->request->param('share_user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            $type=intval(trim($this->request->param('type')));
           //微信请求
            if($type==1){
                $this->redis->setcountinfo();
            }
            if (empty($hbId)  || empty($userId) || empty($uuid) || empty($token)||$userId<0||$hbId<0)
                return returnJson([], '参数错误', -1);

            //验证红包信息
            $mHbInfo = $this->checkHbValidInfo( $hbId);
            if (empty($mHbInfo)) return returnJson([], '红包过期或不存在', -2);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            $ownMoney = $this->redis->hgetusermoeny($userId, $hbId);//自己当前拆的
            $ownMoney = empty($ownMoney) ? 0 : json_decode($ownMoney,true)['money'];

            $totalMoney = $this->redis->getincrbyfloatmoney($hbId);//累积拆的
            $totalMoney = empty($totalMoney) ? 0 : $totalMoney;

            //用户头像信息
            $list = $this->redis->hgetallmoney($hbId);
            if(count($list)>=self::HB_LIST_MAX){
                $list=array_slice($list,0,self::HB_LIST_MAX);
            }
            $data = [];
            $newTime=time();
            if (!empty($list)) {
                foreach ($list as $k => $v) {
                    $data[$k]['user_id'] = $k;
                    $data[$k]['money'] = json_decode($v,true)['money'];
                    $data[$k]['money_time'] = json_decode($v,true)['money_time'];
                    $data[$k]['now_time'] = $newTime;
                }

                $data = array_values($data);
                $aUids = array_column($data, 'user_id');
                $uids = implode(',', $aUids);
                $domain = config('API_URL_JAVA_MIDDLE') . '/user/getInfoInBulk?user_ids=';
                $param = $uids . '&fields=nickname,avatar,username';
                $mUserInfo = httpGet($domain . $param);
                $aUserInfo = json_decode($mUserInfo, true);
                if ($aUserInfo['result'] == 1 && isset($aUserInfo['users'])) {
                    $realUser = $aUserInfo['users'];
//
                    foreach ($data as $kd => $vd) {
                        !isset($data[$kd]['avatar']) && $data[$kd]['avatar']='';
                        !isset($data[$kd]['nickname']) && $data[$kd]['nickname']='';
                        foreach ($realUser as $kr => $vr) {
                            if ($vd['user_id'] == $vr['userId']) {
                                $data[$kd]['avatar'] = $vr['avatar'];
                                $data[$kd]['nickname'] = empty($vr['nickname']) ? substr_replace($vr['username'], '****', 3, 4) : $vr['nickname'];;
                            }
                        }
                    }
                }
            }
            return returnJson(['user_moeny' => $ownMoney, 'total_money' => $totalMoney, 'list' => $data], '请求成功', 1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }

    //app 返回接口数据
    public function revertinfo()
    {
        try {
            $userId = intval(trim($this->request->param('user_id')));
            $hbId = intval(trim($this->request->param('hb_id')));
//            $shareUserId = intval(trim($this->request->param('share_user_id')));
            $uuid = trim($this->request->param('uuid'));
            $token = trim($this->request->param('token'));
            if (empty($hbId)  || empty($userId) || empty($uuid) || empty($token)||$userId<0||$hbId<0)
                return returnJson([], '参数错误', -1);

            //验证红包信息
            $mHbInfo = $this->checkHbValidInfo( $hbId);
            if (empty($mHbInfo)) return returnJson([], '红包过期或不存在', -2);

            //验证用户身份
            $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
            if (empty($userCheckInfo))
                return returnJson([], '用户身份验证失败', -3);

            $totalMoney = $this->redis->getincrbyfloatmoney($hbId);//累积拆的
            $totalMoney = empty($totalMoney) ? 0 : $totalMoney;
            return returnJson(['total_money' => $totalMoney, 'times' => time()], '请求成功', 1);
        } catch (\Exception $exception) {
            return returnJson([], '系统异常', -1);
        }
    }


    //注册回调数据
    public function fivehbreginfo()
    {
  echo 'ok';exit;
        try {
            $mInfo = file_get_contents("php://input");
            $aInfo = json_decode($mInfo, true);
//
            if($aInfo['activity_type']==15){
                Log::info('五元红包注册回调数据,时间：' . date('Y-m-d H:i:s') . '数据为：' . $mInfo);
                $data = [
                    'share_user_id' => $aInfo['s_user_id'],
                    'user_id' => $aInfo['user_id'],
                    'add_time' => time(),
                    'type' => $aInfo['activity_type'],
                    'is_reg'=>!isset($aInfo['is_reg'])?1:$aInfo['is_reg'],
                    'reg_time'=>!isset($aInfo['create_time'])?time():$aInfo['create_time'],
                ];
                $mUserReg = $this->service->addhbuserreg($data);
                Log::info('请求参数为：' . json_encode($data) . '返回信息为：' . $mUserReg);
                if (!empty($mUserReg)) {
                    Log::info('五元红包注册回调数据,注册成功,注册数据为:'.json_encode($data).',id:'.$mUserReg);
                    echo 'ok';exit;
                }else{
                    Log::info('五元红包注册回调数据,注册失败,注册数据为:'.json_encode($data));
                    echo 'error';exit;
                }

            }else{
                Log::info('五元红茶注册回调数据,非正确活动id数据');
                echo 'ok';exit;
            }

        } catch (\Exception $exception) {
            Log::info('五元红包回调注册失败,时间为：' . date('Y-m-d'));
            echo 'error';exit;
        }

    }

    //获取access_token 返回小程序二维码url地址
    public function getwxacode()
    {
        try{
          $goodsId=intval(trim($this->request->param('goods_id')));
          $userId = intval(trim($this->request->param('user_id')));
          $uuid = trim($this->request->param('uuid'));
          $token = trim($this->request->param('token'));
          $earnId=intval(trim($this->request->param('earn_id')));
          $sUserId=intval(trim($this->request->param('s_user_id')));
          $iszhuan=intval(trim($this->request->param('iszhuan')));
          $activityType=trim($this->request->param('activity_type'));
          $activityName=trim($this->request->param('activityName'));

          if(empty($goodsId)||empty($userId)||empty($uuid)||empty($token)||$userId<0||$goodsId<0)
              return returnJson([],'参数不能为空',-1);

//          $redisUrl=$this->redis->getqrcodeinfo($goodsId);
//          if(!empty($redisUrl)) return returnJson(['wx_url'=>$redisUrl],'请求成功',1);

          //验证用户身份
          $userCheckInfo = $this->checkUserToken($userId, $uuid, $token);
          if (empty($userCheckInfo))
              return returnJson([], '用户身份验证失败', -3);

          //获取access_token
          $grpcService=new GrpcService();
          $accessToken=$grpcService::getGoAccessToken();
          if(empty($accessToken))
              return returnJson([],'获取ACCESS_TOKEN失败',-1);

          //生成本地图片
          $params=$this->service->configInfo('goodsInfo',$goodsId,$earnId,$sUserId,$iszhuan,$activityType,$activityName);
          $info=$this->service->curwxcode($accessToken,$params,$goodsId);
          if(empty($info)) return returnJson([],'生成二维码失败',-1);

          //上传图片到阿里云
          $baseImg = chunk_split(base64_encode(file_get_contents($info)));
          $imgType = getimagesize($info);
          $imgInfoMime['mime']='image/png';
          $imgType=empty($imgType)?$imgInfoMime:$imgType;
          $uploadImg = $grpcService::UploadImage(GrpcService::OSS_PATH_AVATAR, $baseImg, '.png' , $imgType['mime'], false);
          if(empty($uploadImg)) return returnJson([],'上传图片失败',-1);
          unlink($info);
//          $this->redis->setqrcodeinfo($goodsId,$uploadImg);
          return returnJson(['wx_url'=>$uploadImg],'请求成功',1);
      }catch (\Exception $exception){
          return returnJson([], '系统异常', -1);
      }

    }


    public function delgoodswxqrcodeinfo()
    {
        $goodsId=intval(trim($this->request->param('goods_id')));
        $res=$this->redis->delqrcodeinfo($goodsId);
        if(!empty($res)) return returnJson([],'goods_id删除图片二维码缓存成功,id:'.$goodsId,1);
        return returnJson([],'goods_id删除图片二维码缓存失败,id:'.$goodsId,-1);
    }



  //分发具体业务数据

//    public function fivehbreginfo($aInfo)
//    {
//
//        try {
//            if(empty($aInfo)){
//                return 'ok';
//            }
//            $data=[];
//            foreach ($aInfo as $k=>$v){
//                $data[]=json_decode($v,true);
//            }
//
//            $times=time();
//            $dataArr=[];
//           foreach ($data as $nk=>$nv){
//               $dataArr[$nk]['share_user_id']=$nv['s_user_id'];
//               $dataArr[$nk]['user_id']=$nv['user_id'];
//               $dataArr[$nk]['type']=$nv['activity_type'];
//               $dataArr[$nk]['reg_time']=$nv['create_time'];
//               $dataArr[$nk]['is_reg']=$nv['is_reg'];
//               $dataArr[$nk]['add_time']=$times;
//           }
//
//            $mUserReg = $this->service->addhbuserreg($dataArr);
//            Log::info('请求参数为：' . json_encode($dataArr) . '返回信息为：' . $mUserReg);
//            if (!empty($mUserReg)) {
//                return 'ok';
//            }
//            return 'error';
//        } catch (\Exception $exception) {
//            Log::info('五元红包回调注册失败,时间为：' . date('Y-m-d'));
//            return 'error';
//        }
//
//    }



    /*
    * 微信分享配置
    */
    public function shareConf($url)
    {
            $ticket=$this->service->getticket();
            if(empty($ticket)) return returnJson([],'获取js_ticket失败',-1);
            if(empty($url)) return returnJson([],'url不能为空',-1);
            $res=$this->service->shareinfo($ticket,$url);
            echo json_encode($res);

    }


    //有效域名 需要按照顺序在微信后台配置
    public function validdomain()
    {
      try{
          //域名池列表
          $domainArr = [
//              '1'=>'https://www.gevanco-sz.com',
              '1'=>'www.qingjiufalv.com',
              '2'=>'www.hhjsjyth.com',
              '3'=>'www.skyviewpacs.com',
              '4'=>'www.hjseniorcare.com',
              '5'=>'www.daohaikeji.com',
              '6'=>'www.joujoushop.com',
          ];
          //type 1:wechat 2 app
//          $type=$this->request->param('type');
//          if(empty($type)) return returnJson(['domain'=>$domainArr[1]],'类型不能为空',1);
//          if($type!=1) return returnJson(['domain'=>$domainArr[1]],'来源为非微信请求',1);

          //获取域名当前是第几个
          $domainNum=$this->redis->getdomainnum();
          if(empty($domainNum)){
              //添加访问记录时间
              $this->redis->setcountinfo();
              $this->redis->setdomainnum();
          }
          $domainNum=$this->redis->getdomainnum();
          if(!array_key_exists($domainNum,$domainArr)){
              end($domainArr);
              $domainNum=key($domainArr);
          }

          $domainStartUrl=$domainArr[$domainNum];
          $httpCode=$this->checkdomain($domainStartUrl);
//          var_dump($httpCode);exit;
          if($httpCode!=200){
              $this->redis->setcountinfo();
              $this->redis->setdomainnum();
              $domainNum=$this->redis->getdomainnum();
          }

          //1:wecht 2:other
          if(array_key_exists($domainNum,$domainArr)){
              $domainUrl=$domainArr[$domainNum];
          }else{
              $domainUrl=$domainArr[1];
          }
          $redisTime=$this->redis->getcountinfo();
          $nowTime=time();

          //最后一次的时间跟现在相比 小于三小时,不需要更换域名 todo 上线之前改3小时
          if(($nowTime-$redisTime)<=60*60*3){
              return returnJson(['domain'=>'https://'.$domainUrl],'请求成功',1);
          }else{
              //添加访问记录时间
              $this->redis->setcountinfo();
              //域名Key 移动到下一个
              $this->redis->setdomainnum();
              $newNum=$domainNum+1;
              if($newNum>6||!array_key_exists($newNum,$domainArr)){
                  end($domainArr);
                  $lastKey=key($domainArr);
                  return returnJson(['domain'=>'https://'.$domainArr[$lastKey]],'最后一个域名了',1);
              }
              return returnJson(['domain'=>'https://'.$domainArr[$newNum]],'更新新域名了',1);

          }
      }catch (\Exception $exception){
          return returnJson([], '系统异常', -1);
      }


    }

    //判断链接是否有效
   public function checkdomain($url)
    {
        $ch = curl_init();
        $timeout = 1;
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $httpcode;

    }


    //手动更换域名
    public function sethandurl()
    {
        $num=$this->request->param('url_num');
        if(empty($num)||$num<0) return returnJson([],'参数不能为空',1);
        $this->redis->setcountinfo();
        $this->redis->writedomain($num);
        return returnJson([],'域名手动更新成功',1);
    }


    //根据id获取赚赚分享的参数值
    public function getShareParams()
    {
        try{
            $shareId=intval(trim($this->request->param('share_param_id')));
            if(empty($shareId)||$shareId<0) return returnJson([],'分享Id不能为空',-1);
            $redisInfo=$this->redis->getearnshareparams($shareId);
            if(!empty($redisInfo)) return returnJson(['share_param'=>$redisInfo],'请求成功',1);
            $info=$this->service->getEarnShareParams($shareId);
            $this->redis->setearnshareparams($shareId,$info);
            return returnJson(['share_param'=>$info],'请求成功',1);
        }catch (\Exception $exception){
            return returnJson([], '系统异常', -1);
        }

    }

    //测试接口
//    public function testapi()
//    {
//        $goodsId='11111111';
//        $earnId='11111111111111111111';
//        $sUserId='11111111111111111111';
//        $iszhuan=1;
//        $activityName='aaaaaaaaaa';
//        $activityType=15;
//        $params=$this->service->configInfo('goodsInfo',$goodsId,$earnId,$sUserId,$iszhuan,$activityType,$activityName);
//        var_dump($params);exit;
//
//
//    }
}
