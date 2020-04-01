<?php
namespace app\anniversary\controller;
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/6/28
 * Time: 18:04
 */
use think\cache\driver\Redis;
use think\Controller;
use think\Db;
use think\Exception;

class Sign extends Common
{
    const KEY = 'FISSILE_ANNIVERSARY_SIGN_';
    const COMMONKEY = 'FISSILE_ANNIVERSARY_';
    const SIGN = 'anniversary_sign';
    const SETTING = 'setting';
    const SETKEY = 'anniversary_celebrate_sign_coupon';

    //缓存时间
    const TWODAY = 172800;
    const ONEDAY = 86400;
    const EXPIRE = 900;

    const CYCLE = 7;//周期
    const NUM = 20;//滚动数量

    //优惠券ID
    const HALFYUAN = 173;//66
    const ONEYUAN = 168;//63
    const TWOYUAN = 169;//64
    const THREEYUAN = 171;//65
    const FIVEYUAN1 = 176;//68
    const FIVEYUAN2 = 178;//68

    public $openTime;

    public function __construct()
    {
        parent::__construct();
    }

    public function dayCouponList()
    {
        $couponIds = $this::HALFYUAN.','.$this::ONEYUAN.','.$this::TWOYUAN.','.$this::THREEYUAN.','.$this::FIVEYUAN1.','.$this::FIVEYUAN2;
        $couponList = $this->getCouponList($couponIds);
        $couponList = array_column($couponList,'discount','id');

        $coupon=[
            1=>[
                'coupon_id'=>$this::HALFYUAN,//66,//173,
                'amount'=>0.5,
            ],
            2=>[
                'coupon_id'=>$this::ONEYUAN,//63,//168,
                'amount'=>1,
            ],
            3=>[
                'coupon_id'=>$this::ONEYUAN,//63,//168,
                'amount'=>1,
            ],
            4=>[
                'coupon_id'=>$this::TWOYUAN,//64,//169,
                'amount'=>2,
            ],
            5=>[
                'coupon_id'=>$this::TWOYUAN,//64,//169,
                'amount'=>2,
            ],
            6=>[
                'coupon_id'=>$this::THREEYUAN,//65,//171,
                'amount'=>3,
            ],
            7=>[
                'coupon_id'=>$this::FIVEYUAN2,//68,//178,//前期176
                'amount'=>5,
            ],
        ];

        foreach($coupon as $k=>$v){
            foreach ($couponList as $k1=>$v1){
                if($v['coupon_id']==$k1){
                    $coupon[$k]['amount'] = floatval($v1);
                }
            }
        }
        return $coupon;
    }

    public function dayCoupon($day=1)
    {
        $key = $this::KEY.'DAYCOUPON';
        $coupon = $this->redis->get($key);
        if(!$coupon){
            $coupon = $this->dayCouponList();
            $this->redis->set($key,$coupon,$this::EXPIRE);
        }
        return $coupon[$day];
    }


    public function today()
    {
        return date('Y-m-d');
    }
    public function yesterday()
    {
        return date('Y-m-d',strtotime('-1 day'));
    }
    public function tomorrow()
    {
        return date('Y-m-d',strtotime('+1 day'));
    }

    public function addSign($data)
    {
        $add=Db($this::SIGN)->insert($data);
        return $add;
    }

    /*
     * 第七天券  15min
     */
    public function getCoupon()
    {
        $key = $this::COMMONKEY.'SIGN_COUPON';
        $ids = $this->redis->get($key);
        if(!$ids){
            $where['set_key']=$this::SETKEY;
            try{
                $res = Db($this::SETTING)->where($where)->column('set_value');
            }catch(Exception $e){
                $res = [];
                $this->apiLog($where,$e->getMessage().':SIGN-GETCOUPON');
            }
            $ids=isset($res[0])?$res[0]:$this->dayCoupon(7)['coupon_id'];
            //如果后台设置多张，只取第一张
            if(stripos($ids,',')){
                $ids = explode(',',$ids);
                $ids =$ids[0];
            }
            $this->redis->set($key,$ids,$this::EXPIRE);
        }
        return $ids;
    }


    /*
     * 滚动条
     */
    public function scroll()
    {
        $res = [];
        $userIconArr = array_rand(range(1, 654), $this::NUM);
        $nickArr = config("nickname");
        $price=[2,3,88];
        for ($i = 0; $i < $this::NUM; $i++) {
            $res[] = [
                'user' => $nickArr[$userIconArr[$i]],
//                'userIcon' => 'http://' . config('DOMAIN_TJJ_UPLOAD') . '/group/userIcon/1' . $userIconArr[$i] . '.jpg',
                'time' => mt_rand(1, 59),
                'price' =>$price[array_rand($price)],
            ];
        }
        $this->returnSuccess(1,$res);
    }


    /*
     * 签到连续次数
     * $time  0:到昨天为止   1:到今天为止
     */
    public function lastSign($user_id=123,$time=1)
    {
        $where['user_id'] = $user_id;
        $where['create_time'] = $time?['gt',strtotime('today')]:['between',[strtotime('yesterday'),strtotime('today')]];
        try{
            $day = Db($this::SIGN)->field('day')->where($where)->order('create_time desc')->find();
        }catch(Exception $e){
            $day = 0;
            $this->apiLog($where,$e->getMessage().':SIGNINFO-LASTSIGN');
        }
        $day = $day?$day['day']:0;
        return $day;
    }

    /*
     * 已连续签到天数
     */
    public function conDay($user_id=789,$time=0)
    {
        $date=$time?$this->today():$this->yesterday();
        $key = $this::KEY.'SIGN:'.$user_id.':'.$date;
        $day = $this->redis->get($key);
        if($day==='0'){
            return $day;
        }
        if(!$day){
            //查库
            $day=$this->lastSign($user_id,$time);
            $this->redis->set($key,$day,$this::EXPIRE);
        }
        return $day;
    }

    /*
     * 签到列表详情
     * $time  0:到昨天为止   1:到今天为止
     */
    public function signInfo($user_id=123,$time=0)
    {
        $createTime=$time?strtotime('tomorrow'):strtotime('today');
        $where['user_id'] = $user_id;
        $where['create_time'] = ['lt',$createTime];
        $limit = $this->lastSign($user_id,$time);
        $day=[];
        if($limit>0){
            try{
                $day = Db($this::SIGN)->field('day,amount')->where($where)->order('create_time desc')->limit(0,$limit)->select();
                krsort($day);
                $day = array_values($day);
            }catch(Exception $e){
                $day = [];
                $this->apiLog($where,$e->getMessage().':SIGNINFO-limit.'.$limit);
            }
        }
        return $day;
    }

    /*
     * 已连续签到天数  time=0昨天   1：今天
     */
    public function signDay($user_id=123456,$time=0)
    {
        $date=$time?$this->today():$this->yesterday();
        $key = $this::KEY.'SIGNINFO:'.$user_id.':'.$date;
        $day = $this->redis->get($key);
        if($day===[]){
            return $day;
        }
        if(!$day){
            //查库
            $day=$this->signInfo($user_id,$time);
            $count = count($day);
            for($i=0;$i<$count;$i++){
                $day[$i]['state']=1;
            }
            $this->redis->set($key,$day,$this::EXPIRE);
        }
        return $day;
    }


    public function signList($user_id,$token,$uuid)
    {
        $this->checkUser($user_id,$token,$uuid);

        $todayInfo = $this->signDay($user_id,1);
        $count = count($todayInfo);
        $state=-1;
        if($count>0){
            $data=$todayInfo;
        }else{
            $yesInfo =  $this->signDay($user_id);
            $data=$yesInfo;
            $count = count($data);
            $count = $count>=7?0:$count;
            $state=0;
        }

        for($i=$count;$i<$this::CYCLE;$i++){
            $day = $i+1;
            $data[$i]['day']=$day;
            $data[$i]['state']=$i==$count?$state:-1;
            $data[$i]['amount']=$this->dayCoupon($day)['amount'];
        }

        $this->returnSuccess(1,$data);
    }


    /*
     * 签到
     */
    public function sign($user_id,$token,$uuid)
    {
        $this->checkUser($user_id,$token,$uuid);
        $this->isBlack($user_id);
        $now = $this->signDay($user_id,1);
        if(count($now)>0){
            $this->returnError(-32);
        }

        $yesterday=$this->signDay($user_id);
        $day=count($yesterday)+1;
        $day=$day>7?1:$day;

        $couponDetail = $this->dayCoupon($day);
        //如果是第七天 查库获取优惠券id
        $coupon_id = $day==7?$this->getCoupon():$couponDetail['coupon_id'];
        $amount=$couponDetail['amount'];//实际取返回值
        $data=[
            'user_id'=>$user_id,
            'coupon_id'=>$coupon_id,
            'day'=>$day,
            'amount'=>$amount,
            'create_time'=>time(),
        ];

        Db::startTrans();

        try {
            $add = Db($this::SIGN)->insert($data);
        }catch(Exception $e) {
            $message = $e->getMessage();
            $this->apiLog($_REQUEST,$message,$data);
            $this->returnError(-47,$message);
        }
        //领券接口
        $getCoupon = $this->getPlatformCoupon($user_id,$coupon_id);
        if($getCoupon['result']==1){
            Db::commit();
            $key = $this::KEY;
            $end = $user_id.':'.$this->today();
            $keys = [
                $key.'SIGN:'.$end,
                $key.'SIGNINFO:'.$end,
            ];
            $this->resetKey($keys);

            if($getCoupon['data'][0]['discount']!=$amount){
                $this->apiLog($_REQUEST,'领取面额不一致',$getCoupon['data'][0]);
            }
            $this->returnSuccess(1,$data);

        }else{
            Db::rollback();
            $message = isset($getCoupon['message'])?$getCoupon['message']:'Wap/Coupon/receiveFullCoupon 领取失败';
            $this->returnError(-50,$message);
        }
    }


    /*
     * 小游戏(当前用户今日     是否已签到)
     */
    public function isSign($user_id,$token,$uuid)
    {
        $this->checkUser($user_id,$token,$uuid);
        $todayInfo=$this->conDay($user_id,1);
        $isSign=$todayInfo>0?1:0;
        $this->returnSuccess(1,$isSign);
    }




    /***************************************************以下仅做测试使用****************************************************/
    /*public function testCoupon($user_id,$coupon_id)
    {
        $params = [
            'user_id'=>$user_id,
            'stringCoupon'=>$this->couponIds($coupon_id),
            'result'=>1,
            'is_post'=>1
        ];
        $host = config('DOMAIN_API_TJJ_SERVICE');
        $getCoupon = api('Wap/Coupon/receivePlatformCoupon',$params,false,$host);
        dump($getCoupon);
    }


    public function updateSignFor($id,$time,$key)
    {
        if($key=='skndjj78hrunde483'){
            $update = Db($this::SIGN)->where('id='.$id)->update(['create_time'=>$time]);
            return $update;
        }
    }*/



}