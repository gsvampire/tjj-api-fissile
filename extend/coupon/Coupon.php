<?php

/**
 * 闪电降价优惠券领取公共类
 * Created by lixiang.
 * User: all
 * Date: 2016/10/25
 * Time: 10:52
 */
namespace coupon;
use SplSubject;
use Think\Cache\Driver\Redis;
use Think\Model;

class Coupon extends Model implements \SplSubject
{
    #####################优惠券属性定义########################
    protected $observer; //观察者
    public $main_id = 0; //优惠券住表Id
    public $type = 0;  //0抵用券，1：现金券
    public $user_id;   //用户id
    public $special_id;//专区id
    public $coupon_name;//优惠券名称
    public $amount;    //使用条件金额
    public $discount;  //优惠券面额
    public $undertaker_type = 1; //优惠券承担对象
    public $start_time;  //优惠券开始时间
    public $end_time;    //优惠券使用结束时间
    protected $state ; //优惠券种类
    protected $disposable = 0;//是否为一次性领取
    public $add_time;  //优惠券添加时间
    public $special_dicount ; //单独领取专区优惠券传入面额 【单独领取】
    public $all_mainid ;  //单独领取一张全场券需要的main_id
    public $cache_time = 604800; //一个礼拜缓存时间
    public $getcouponRepeat = 0; //是否可以重复领取优惠券， 0：不可以重复领取相同优惠券 1：可以重复领取相同优惠券
    public $coupon = array();
    public $resultDie = 1; //是否从结果处中断 1:中断 0：不中断
    public $errorData;
    public $range;      //跨专区优惠券  0不启用，1启用
#########################优惠券表明定义###################################
    const TABLE_SPECIAL = 'special';
    const TABLE_COUPON_MAIN = 'activity_coupon_main';
#########################状态提示定义#####################################
    protected $error = [
        '-1' => '全场券主表ID错误！',
        '-2' => '专区券需要设置专区ID！',
        '-3' => '该专区没有优惠券或已领完~',
        '-4' => '优惠券领取数据错误~',
        '-5' => '优惠券领取失败~',
        '-6' => '您已经领过该优惠券~',
    ];

    protected $success = [
        1 => '领取成功！请至我的优惠券中查看！',
        2 => '全场优惠券领取成功',
        3 => '专区优惠券领取成功'
    ];
##########################缓存名称自定义#####################################
    public $key_special_alone = 'wap_coupon_special_alone'; //定义单独领取专区优惠券缓存   $key. user_id . special .  discount
    public $key_special_all = 'wap_coupon_special_all'; //定义全部领取专区优惠券缓存        $key. user_id . special
    public $key_all_alone = 'wap_coupon_all_alone'; //定义单独领取全场优惠券缓存  $key. user_id . main_id
    public $key_all_all = 'wap_coupon_all_all'; //定义全部领取全场优惠券缓存   $key .user_id. main_ids
    public $key ; //全局redis的key
    public $value ; //全局redis的value
    public $redis; //设置redis对象
##########################属性定义结束######################################
    /**
     * Coupon constructor.
     */
    public function __construct()
    {
        $this->redis = new Redis();
        $this->add_time = time();
    }
    /**
     * 优惠券结构
     */
    public function couponStruct(){
        if ($this->state == 1 && $this->disposable == 1 &&$this->main_id === 0 ){
            $this->error('-1');
        }elseif ($this->state == 0 && !isset($this->special_id)){
            $this->error('-2');
        }
         $coupon = [
             'user_id' => $this->user_id,
             'main_id' => $this->main_id,
             'type' => $this->type,
             'coupon_name' => $this->coupon_name,
             'activity_id' => $this->special_id,
             'amount' =>$this->amount,
             'discount' => $this->discount,
             'range' => $this->range,
             'undertaker_type' => $this->undertaker_type,
             'start_time' => $this->start_time,
             'end_time' => $this->end_time,
             'add_time' => time(),

         ];
        return $coupon;
    }
    /**
     * 全场券结构组成部分
     */
    public function AllCoupon(){
        if ($this->disposable == 1 && $this->main_id === 0){
            $this->error(-1);
        }
        $this->special_id = 0;
        $this->main_id = $this->disposable === 1 ? $this->main_id : $this->all_mainid;

        $data = $this->returnModel(self::TABLE_COUPON_MAIN)->where(array('id' => array('IN', "{$this->main_id}"), 'is_deleted' => 0))->select();
        //组装全场优惠券数据
        $start_time = null;//[新增需求 可选取时间段]
        $end_time = null;
        foreach ($data as $key => $val){
            $this->main_id = $val['id'];
            $this->coupon_name = $val['coupon_name'];
            $this->type = $val['type'];
            $this->amount = $val['amount'];
            $this->discount = $val['discount'];
            $this->undertaker_type = $val['undertaker_type'];
            $this->no_use_full_coupon = $val['no_use_full_coupon'];
            $this->no_use_vip = $val['no_use_vip'];
            $this->range =empty($this->range) ? $val['range']:$this->range;
            $start_time = $val['time_type'] == 1 ?  time(): $val['start_time'];
            $this->start_time = empty($this->start_time) ? $start_time : $this->start_time;
            $end_time = $val['time_type'] == 1 ?  time() + ((int)$val['time_slot']*3600) : $val['end_time'];
            $this->end_time = empty($this->end_time) ?  $end_time : $this->end_time;
            if ($this->disposable == 0 && $val['id'] == $this->all_mainid){
                $this->coupon = $this->couponStruct();
            }elseif($this->disposable == 1){
                $this->coupon[] = $this->couponStruct();
            }
        }
    }
    /**
     * 专区券结构组成部分
     * 如果当时单独领取，必须传递special_dicount 的值
     */
    public function SpecialCoupon(){
        if (!isset($this->special_id)){
            $this->error('-2');
        }
        $config = $this->returnModel(self::TABLE_SPECIAL)->field('start_time, end_time, title,coupon_configs')->where(array('special_id' => $this->special_id, 'use_coupon' => 1))->find();
        if (empty($config)){
            $this->error('-3');
        }
        $this->start_time = $config['start_time'];
        $this->end_time = $config['end_time'];

        $couponData = (array)json_decode($config['coupon_configs']);
        //组装专区优惠券数据
        foreach ($couponData['data'] as $key => $val) {
            $this->coupon_name = $config['title'] . "满{$val->useCondition}减{$val->denomination}";
            $this->amount = $val->useCondition;
            $this->discount = $val->denomination;
            $this->range = 1;
            if ($this->disposable == 0 && $val->denomination == $this->special_dicount){
                $this->coupon = $this->couponStruct();
                break;
            }elseif ($this->disposable == 1){
                $this->coupon[] = $this->couponStruct();
            }

        }
        if(empty($this->coupon)){
            $this->error('-4');
        }
    }

    
    /**
     * 返回实例化MODEL
     * @param $tableName 数据表名称
     * @return \Model
     */
    public function returnModel($tableName){
        return M($tableName);
    }

    /**
     * Attach an SplObserver
     * @link http://php.net/manual/en/splsubject.attach.php
     * @param SplObserver $observer <p>
     * The <b>SplObserver</b> to attach.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function attach(SplObserver $observer)
    {
        // TODO: Implement attach() method.
        $this->observer[] = $observer;
    }

    /**
     * Detach an observer
     * @link http://php.net/manual/en/splsubject.detach.php
     * @param SplObserver $observer <p>
     * The <b>SplObserver</b> to detach.
     * </p>
     * @return void
     * @since 5.1.0
     */
    public function detach(SplObserver $observer)
    {
        // TODO: Implement detach() method.
        if ($key = array_search($observer, $this->observer)){
            unset($this->observer[$key]);
        }
    }

    /**
     * Notify an observer
     * @link http://php.net/manual/en/splsubject.notify.php
     * @return void
     * @since 5.1.0
     */
    public function notify()
    {
        // TODO: Implement notify() method.
        foreach ($this->observer as $observer){
            $observer->update($this);
        }
    }
    //领取优惠券
    public function getCoupon(){
        $this->notify();
    }

    //缓存判断是否重复领取
    public function checkReids(){
        if ($this->redis->SISMEMBER($this->key, $this->value) && $this->getcouponRepeat == 0){
            if($this->resultDie === 0){
                return $this->error(-6);
            }
            $this->error(-6);
        }
    }

    //生产key值
    public function productKey(){
        ##################################################
        if($this->state == 1){
            $this->key = $this->disposable === 1 ? $this->key_all_all : $this->key_all_alone;
            $this->value = $this->disposable === 1 ? "{$this->user_id}_{$this->main_id}" : "{$this->user_id}_{$this->all_mainid}";
        }else{
            $this->key = $this->disposable === 1 ? $this->key_special_all : $this->key_special_alone;
            $this->value = $this->disposable === 1 ? "{$this->user_id}_{$this->special_id}" : "{$this->user_id}_{$this->special_id}_{$this->special_dicount}";
        };
    }
    //设置redis
    public function setRedis(){
        $this->redis->sadd($this->key, $this->value);
        $this->redis->expire($this->key, $this->cache_time);
//        S($this->key, 1, $this->cache_time);
    }
    //触发领取行为
    public function haveCoupon($user_id , $state, $disposable){
        //领取行为赋值
        $this->user_id = $user_id;
        $this->disposable = $disposable;
        $this->state = $state;
        //设置redis
        $this->productKey();
        $this->checkReids();
        //入口判断券的种类以及领取方式
        $this->state === 1 ? $this->AllCoupon() : $this->SpecialCoupon();
        return $this->coupon;
    }

    //设置return模式
    public function returnData(){
        $errorMessage = json_decode($this->errorData);
        if (is_object($errorMessage)){
            return (array)$errorMessage;
        }

        return $this->getDo();
    }

    //just do it!
    public function getDo(){
        if($this->disposable == 1 && $this->returnModel('user_activity_coupon')->addAll($this->coupon)){
            if (!$this->getcouponRepeat){
                $this->setRedis();
            }
            return $this->success(1);
        }elseif($this->disposable == 0 && $this->returnModel('user_activity_coupon')->add($this->coupon)){
            if (!$this->getcouponRepeat){
                $this->setRedis();
            }
            return $this->success(1);
        }else{
            $this->error('-5');
//            return $this->returnData();
        }
    }

    //错误返回结果
    public function error($code){
        $res = (json_encode(array_combine(array('result','message') , array($code, $this->error[$code])))) ;
        if ($this->resultDie){
            exit($res) ;
        }else{
            $this->errorData = $res;
        }

    }
    //正确结果返回
    public function success($code, $data){
        $res = [
            'result' => 1,
            'message' => $this->success[$code],
            'data' => $data,
        ];
        if ($this->resultDie){
            exit(json_encode($res)) ;
        }else{
            return $res;
        }
    }
}

class Observer implements \SplObserver{
    public $user_id;
    public $disposable;
    public $state;
    public $coupon;
    public $table = 'user_activity_coupon';
    /**
     * Receive update from subject
     * @link http://php.net/manual/en/splobserver.update.php
     * @param int $user_id 用户id
     * @param int $state 优惠券类型 1：全场券 0：专区全
     * @param int $disposable 领取方式 1：一次性领取全部， 0：单独领取
     * @since 5.1.0
     */
    public function __construct($user_id , $state, $disposable)
    {
        $this->user_id = $user_id;
        $this->disposable = $disposable;
        $this->state = $state;
    }

    //优惠券目标回调类
    public function update(SplSubject $subject)
    {
        // TODO: Implement update() method.
        $this->coupon = $subject->haveCoupon($this->user_id, $this->state, $this->disposable);
        if ($subject->resultDie){
            $subject->getDo();
        }
    }
}

##########################################################################
#使用方法   该类可以实现  专区券或全场券 单独或全部领取 可以重复或者只允许相同券领一次#
##########################################################################
#例：
#$coupon = new \Home\Classcomm\Coupon();
#$coupon->special_id = 5368; 设置专区id 【领取专区全部优惠券或单独领取一张专区优惠券 必传参数】
#$coupon->special_dicount = 10; 设置领取优惠券的面额【领取单独领取一张专区优惠券 必传参数】
#$coupon->getcouponRepeat = 1; 设置该张优惠是否可以重复领取
#
#$coupon->main_id = '25,26,27,28';  设置领取全场优惠券main_id 必须参数
#$coupon->all_mainid = 28;   设置领取全场单独一张优惠券main_id 必须参数


#$people =new \Home\Classcomm\Observer(277811, 0 , 1);   实例化目标者  传递参数  【用户id】,【全场券or专区券】，【全部领取or单独领取一张】
#$coupon->attach($object);  在优惠券注册领取人员
#$coupon->getCoupon();   获取优惠券
#结束



