<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-11
 * Time: 13:49
 */

namespace app\zz\service;

use app\zz\model\FiveHbShareLog;
use app\zz\model\FiveHbUser;
use app\zz\model\FiveHbSplitLog;
use app\zz\model\FiveHbUserReg;
use think\Log;
use app\zz\model\FiveHbShareParam;

//use app\zz\model\FiveHbUserOrder;

class ZActivity extends BaseService
{

    protected $WX_CODE_LIMIT = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=';

    protected $INIT_START='00:00:00';

    const DNS_ACCESS_ID='LTAIOF40bBEQqhlB';

    const DNS_SECRET='idl1nj2e59UyH95FkB72gb2cesitTI';

    const NEW_APP_ID='wx5f6d55a046565540';

    //JAVA 判断新老客 os 的list
    protected $osJavaList = [
        'ios' => 'ios',
        'wechat' => 'wechat',
        'android' => 'android',
    ];

    protected $nickNameList=[
        "离*秋",
        "別*言",
        "红*薄",
        "深*秋",
        "烟**薄",
        "月**河",
        "妲*",
        "十*",
        "醉*楼〃",
        "树*思",
        "弦*落",
        "花*酒",
        "画*骨",
        "执*羞",
        "写*伤",
        "琴**残",
        "七**生",
        "君**在",
        "浮**歇",
        "墨**新",
        "心**源",
        "涙*颜、",
        "离*愁",
        "江*笛",
        "捞*人",
        "听*缠",
        "残**殇、",
        "离**逝",
        "叶*枯。",
        "卿*歌",
        "远**思",
        "太*洋",
        "绿**猗",
        "流*月",
        "忆**乆°",
        "昔*忆",
        "宁*倾",
        "为*下",
        "挽**歌",
        "愁**眼",
        "寻*雨",
        "谁**尘",
        "笙**梦",
        "待*人。",
        "凉*忆",
        "月**歌゛",
        "离*秋",
        "別*言",
        "红*薄",
        "深*秋",
        "烟**薄",
        "月**河",
        "妲*",
        "十*",
        "醉*楼〃",
        "树*思",
        "弦*落",
        "花*酒",
        "画*骨",
        "执*羞",
        "写*伤",
    ];


    public function addsharehb($shareUserId, $userId, $hbId)

    {
        try {
            $arr = [
                'share_user_id' => $shareUserId,
                'user_id' => $userId,
                'day_time' => date('Y-m-d'),
                'add_time' => time(),
                'hb_id' => $hbId,
            ];
            $mHbShare = new FiveHbShareLog();
            $res = $mHbShare->insert($arr);
            return $res;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //用户是否分享过红包
    public function getUserShareHb($shareUserId)
    {
        try {
            $mHbShare = new FiveHbShareLog();
            $info = $mHbShare->where('share_user_id', $shareUserId)
                ->where('day_time', date('Y-m-d'))
                ->value('id');
            return $info;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //获取用户昵称
    public function genNickName()
    {
//        $nickName = config('nickname');
        return $this->nickNameList;
    }

    /**
     * 获用户头像信息
     * @return array
     */
    public function getIcon($num = 20)
    {
        $res = [];
        $userIconArr = array_rand(range(1, 55), $num);
        if ($num == 1) {
            $userIconArr = [$userIconArr];
        }
        $nickArr = $this->genNickName();
        for ($i = 0; $i < $num; $i++) {
            $res[] = [
                'nickname' => $nickArr[$userIconArr[$i]],
                'avatar' => 'https://' . config('DOMAIN_IMG_ADATAR') . '/group/userIcon/1' . $userIconArr[$i] . '.jpg',
            ];
        }
        return $res;
    }

    //判断用户今日是否生成过红包
    public function userhbinfo($userId)
    {
        try {
            $mUserHb = new FiveHbUser();
            $info = $mUserHb->where('user_id', $userId)
                ->where('day_time', date('Y-m-d'))
                ->value('id');
            return $info;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //给用户生成红包记录
    public function getHbId($userId)
    {
        try {
            $mUserHb = new FiveHbUser();
            $arr = [
                'user_id' => $userId,
                'day_time' => date('Y-m-d'),
                'add_time' => time(),
            ];
            $id = $mUserHb->insertGetId($arr);
            return $id;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //判断用户新老客信息 type 0  老客  1，2新客
    public function userStatus($userId, $os, $devId)
    {

        if (!array_key_exists($os, $this->osJavaList) || empty($os)) {
            $os = 'wechat';
        }
        if (empty($devId)) {
            $devId = 'wechat';
        }
        $domain = config('API_URL_JAVA_MIDDLE');
        $url = $domain . '/user/getNewCustomerType?user_id=' . $userId .
            '&os=' . $os . '&dev_id=' . $devId;
        $info = httpGet($url);
        $res = json_decode($info, true);
        if ($res['result'] == 1 && $res['type'] !== 0)
            return 1;
        return 0;
    }

    //邀请人数
    public function inviteNum($userId)
    {
//        try {
//            $account = new EarnAccount();
//            $inviteInfo = $account->where('user_id', $userId)->value('invite_num');
//            return empty($inviteInfo) ? 0 : $inviteInfo;
//        } catch (\Exception $exception) {
//            return 0;
//        }

       try{
           $domain=config('DOMAIN_PHP_MAIN').'/Api2_5_0/activity/earnInviteNum';
           $url=$domain.'?user_id='.$userId;
           $res=httpGet($url);
           Log::info('五元红包,获取赚赚邀请人数,请求信息为:'.$url.' 返回信息为:'.$res);
           $res=json_decode($res,true);
           if($res['result']==1){
               $info=$res['data']['inviteNum'];
           }else{
               $info=0;
           }
           return $info;
       }catch (\Exception $exception){
            return 0;
       }


    }

    //恶意系数-腾讯天域值
    public function badinfo($userId)
    {
//        $domain = config('DOMAIN_ANTI_CHEAT');
//        $url = $domain . '/user/getProperty?userId=' . $userId;
//        $res = httpGet($url);
//        return $res;
       $grpc=new GrpcService();
       $reskInfo=$grpc::userRiskInfo($userId);
       return $reskInfo;
    }

    //拆红包数据入库
    public function addSplitInfo($data = array())
    {
        try {
            $mSplit = new FiveHbSplitLog();
            $info = $mSplit->insertGetId($data);
            return $info;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //添加注册数据
    public function addhbuserreg($data = array())
    {

        try {
            $mUserReg = new FiveHbUserReg();
            $info = $mUserReg->insert($data);
            return $info;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //添加订单数据
//    public function addhbuserorder($data = array())
//    {
//        try {
//            $mUserOrder = new FiveHbUserOrder();
//            $info = $mUserOrder->insert($data);
//            return $info;
//        } catch (\Exception $exception) {
//            return false;
//        }
//    }

    //未打款用户信息查询
    public function getusernomoneyinfo()
    {
        try {
            $mSplit = new FiveHbSplitLog();
            $info = $mSplit->where('status', 1)
                ->field('id,share_user_id,split_money')
                ->find();
            return empty($info) ? $info : $info->toArray();
        } catch (\Exception $exception) {
            return false;
        }
    }

    //二维码配置
    public function configInfo($type = 'goodsInfo', $typeId,$earnId,$sUserId,$iszhuan,$activityType,$activityName)
    {
        try{
            $shareParam=new FiveHbShareParam();
            $arrParams=[
                'earn_id'=>$earnId,
                's_user_id'=>$sUserId,
                'iszhuan'=>$iszhuan,
                'activityName'=>$activityName,
                'activity_type'=>$activityType,
            ];
//            $sParams=http_build_query($arrParams);
            $sParams=json_encode($arrParams);
            $insertArr=[
                'share_params'=>$sParams,
            ];
            $paramId=$shareParam->insertGetId($insertArr);
        }catch (\Exception $exception){
            $paramId='';
        }

        $configs = [
            //默认获取首页助力分享的小程序二维码
            'boostHome' => [
                'page' => 'pages/home/home',//
                'scene' => "order_no=" . $typeId,
                'width' => '280',
                'auto_color' => false,
                'is_hyaline' => true,
            ],
            //获取商品详情二维码
            'goodsInfo' => [
                'page' => 'pages/home/goodsDetail/goodsDetail',
                'scene' => "goods_id=".$typeId."&s_id=".$paramId,
                'width' => '280',
                'auto_color' => false,
                'is_hyaline' => true,
            ],
            //获取店铺详情二维码
            'storeInfo' => [
                'page' => 'pages/home/goodsDetail/shopDetail/shopDetail',
                'scene' => "shopid=" . $typeId . "&type=store_info",
                'width' => '280',
                'auto_color' => false,
                'is_hyaline' => true,
            ],
        ];
        return $configs[$type];
    }

    //生成本地二维码图片
    public function curwxcode($accessToken, $data = array(), $goodsId)
    {
        $wxcodeUrl = $this->WX_CODE_LIMIT . $accessToken;
        $data = json_encode($data);
        $info = $this->api_code_curl($wxcodeUrl, $data);
        if (empty($info)) return false;

        $filePath = WEB_CODE_ROOT . "/upload_dir/zz/";
        if (!is_dir($filePath)) {
            mkdir($filePath, 0777, true);
        }
        //生成新图片名
        $goods_key = 'goods_' . $goodsId;
        $rand=mt_rand(10000,99999);
//        $hcpicName = $filePath . $rand.$goods_key. '_' . date('YmdHis').'_' . 'wx-qr' . ".jpeg";
        $hcpicName = $filePath . $rand.$goods_key. '_' . date('YmdHis').'_' . 'wx-qr' . ".png";
        file_put_contents($hcpicName, $info);
        return $hcpicName;

    }



    protected function api_code_curl($backUrl, $data)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $backUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_TIMEOUT, 3);

        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $result = curl_exec($curl);
        if ($result === false) {
            return false;
        }
        curl_close($curl);
        return $result;
    }

    //判断红包是否失效 数据库层
    public function hbvalidInfo($HbId)
    {
        try{
            $mHb=new FiveHbUser();
            $addTime=$mHb->where('id',$HbId)->value('add_time');
            $initTime=strtotime(date('Y-m-d 00:00:00'));
            if($addTime<$initTime) return false;
            return true;
        }catch (\Exception $exception){
            return false;
        }
    }

    //用户打款后，更新状态
    public function updatestatus($id)
    {
        try{
            $mSplit = new FiveHbSplitLog();
            $info=$mSplit->save(['status'=>2],['id'=>$id]);
            return $info;
        }catch (\Exception $exception){
            return false;
        }
    }


    //获取ticket信息
    public function getticket()
    {
        $grpcService=new GrpcService();
        $ticket=$grpcService::getnewGojsticket();
        return $ticket;
    }

    //分享信息
    public function shareinfo($ticket,$url)
    {
        $nonceStr=$this->getNonceStr();
        $timestamp=time();
        $string = "jsapi_ticket=$ticket&noncestr=$nonceStr&timestamp=$timestamp&url=".$url;
        $sign = sha1($string);
        $config=[
            'appId'=>self::NEW_APP_ID,
            'timestamp'=>$timestamp ,
            'nonceStr'=> $nonceStr,
            'signature'=>$sign,
            'jsApiList'=> ['onMenuShareAppMessage','onMenuShareTimeline','onMenuShareQQ','onMenuShareWeibo','onMenuShareQZone','chooseWXPay','updateAppMessageShareData','updateTimelineShareData','hideMenuItems','showMenuItems']
        ];
        return $config;
    }

    private function getNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }


    public function getEarnShareParams($id)
    {
        try{
            $shareParam=new FiveHbShareParam();
            $info=$shareParam->where('id',$id)->value('share_params');
            return $info;
        }catch (\Exception $exception){
            return false;
        }

    }
}