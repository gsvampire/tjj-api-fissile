<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-10
 * Time: 17:16
 */

namespace app\zz\model;

//use think\Cache;
use think\cache\driver\Redis;

class MRedis
{

    protected $redis;
    protected $config;

    const STRING_FIVE_HB_SHARE_USER = 'string_five_hb_share_user:';

    const SET_FIVE_HB_SHARE_USER = 'set_five_hb_share_user:';

    const STRING_FIVE_HB_ROLL_INFO = 'string_five_hb_roll_info:';

    const STRING_FIVE_HB_CREATE_INFO = 'string_five_hb_create_info:';

    const SET_FIVE_HB_CHECK_NUM_INFO = 'set_five_hb_check_num_info:';

    const STRING_FIVE_HB_HELP_NUM_INFO = 'string_five_hb_help_num_info:';

    const STRING_FIVE_HB_NOW_TOTAL_INFO = 'string_five_hb_now_total_info:';

    const ZSET_FIVE_HB_USER_LOG_INFO = 'zset_five_hb_user_log_info:';

    const HASH_FIVE_HB_USER_LOG_INFO = 'hash_five_hb_user_log_info:';

    const STRING_FIVE_HB_USER_ICON_INFO = 'string_five_hb_user_icon_info:';

    const STRING_QR_CODE_GOODS_URL_INFO='string_qa_code_goods_url_info:';

    const LIST_UNIFY_OUT_ACTIVITY_REG='list_unify_out_activity_reg:';

    const STRING_USER_DAY_TOTAL_NUM_INFO='string_user_day_total_num_info:';

    const STRING_PV_COUNT_INTO='string_pv_count_info:';

    const STRING_DOMAIN_NUM_INFO='string_domain_num_info:';

    const STRING_FIVE_HB_DAY_MONEY_INFO = 'string_five_hb_DAY_money_info:';

    const STRING_FIVE_EARN_SHARE_PARAMS_INFO='string_five_earn_share_params_info:';

    public function __construct()
    {
//        $this->config = config('zzRedis');
//        $this->redis = Cache::connect($this->config)->handler();

        $this->config = new Redis(config('redis'));
        $this->redis = $this->config->handler();
    }

    //用户是否分享过五元红包 写入
    public function setfivehbshare($userId, $hbId)
    {
        $this->redis->SADD(self::SET_FIVE_HB_SHARE_USER . $userId . $hbId, $userId);
        $this->redis->Expire(self::SET_FIVE_HB_SHARE_USER . $userId . $hbId, 86400);
    }

    //用户是否分享过五元红包 读取
    public function getfivehbshare($userId, $hbId)
    {
        return $this->redis->SISMEMBER(self::SET_FIVE_HB_SHARE_USER . $userId . $hbId, $userId);
    }

    //五元红包滚动数据缓存 写入
    public function setfivehbrollinfo($info)
    {
        $this->redis->setex(self::STRING_FIVE_HB_ROLL_INFO . date('Y-m-d'), 3600, json_encode($info));
    }

    //五元红包滚动数据缓存 读取
    public function getfivehbrollinfo()
    {
        return $this->redis->get(self::STRING_FIVE_HB_ROLL_INFO . date('Y-m-d'));
    }

    //五元红包 用户生成五元红包 写入
    public function setfivehbuserinfo($userId, $HbId)
    {
        $this->redis->setnx(self::STRING_FIVE_HB_CREATE_INFO . date('Y-m-d')  . '-' . $HbId, $HbId . '-' . $userId);
        $this->redis->Expire(self::STRING_FIVE_HB_CREATE_INFO . date('Y-m-d'). '-' . $HbId, 86400);
    }

    //五元红包 用户生成五元红包 读取
    public function getfivehbuserinfo( $HbId)
    {
        return $this->redis->get(self::STRING_FIVE_HB_CREATE_INFO . date('Y-m-d') . '-' . $HbId);
    }

    //五元红包 拆红包系数的次数 写入
    public function setchecknuninfo($userId, $hbId)
    {
        $this->redis->sadd(self::SET_FIVE_HB_CHECK_NUM_INFO . date('Y-m-d') . $hbId, $userId);
    }

    //五元红包 用户是否拆过该红包
    public function getusercheckhb($userId, $hbId)
    {
        return $this->redis->SISMEMBER(self::SET_FIVE_HB_CHECK_NUM_INFO . date('Y-m-d') . $hbId, $userId);
    }

    //五元红包 拆红包系数的次数 读取
    public function getchecknuninfo($hbId)
    {
        return $this->redis->scard(self::SET_FIVE_HB_CHECK_NUM_INFO . date('Y-m-d') . $hbId);
    }

    //五元红包 帮拆系数 写入
    public function sethelpnuminfo($userId)
    {
        $this->redis->setnx(self::STRING_FIVE_HB_HELP_NUM_INFO . $userId, 0);
        $this->redis->incr(self::STRING_FIVE_HB_HELP_NUM_INFO . $userId);
        $this->redis->Expire(self::STRING_FIVE_HB_HELP_NUM_INFO, 86400 * 7);
    }

    //五元红包 帮拆系数 读取
    public function gethelpnuminfo($userId)
    {
        return $this->redis->get(self::STRING_FIVE_HB_HELP_NUM_INFO . $userId);
    }

    //五元红包  当前红包已经累计拆分金额 写入
    public function setincrbyfloatmoney($hbId, $money)
    {
        $money=sprintf("%.2f",$money);
        $this->redis->INCRBYFLOAT(self::STRING_FIVE_HB_NOW_TOTAL_INFO . date('Y-m-d') . $hbId, $money);
    }

    //五元红包  当前红包已经累计拆分金额 读取
    public function getincrbyfloatmoney($hbId)
    {
        return sprintf("%.2f",$this->redis->get(self::STRING_FIVE_HB_NOW_TOTAL_INFO . date('Y-m-d') . $hbId));
    }

    //五元红包  用户拆红包记录 zset 写入
//    public function zaddusermoney($userId,$hbId,$money)
//    {
//      $this->redis->zadd(self::ZSET_FIVE_HB_USER_LOG_INFO.$hbId,$money,$userId);
//    }
//
//    //五元红包  用户拆红包记录 zset 读取
//    public function zrangeusermoney($hbId)
//    {
//      return $this->redis->zRange(self::ZSET_FIVE_HB_USER_LOG_INFO.$hbId,0,-1,true);
//    }

    //五元红包 用户红包记录 hash 写入
    public function hsetusermoney($userId, $hbId, $moneyInfo)
    {
        $this->redis->hset(self::HASH_FIVE_HB_USER_LOG_INFO . date('Y-m-d') . $hbId, $userId, json_encode($moneyInfo));
    }

    //五元红包 用户红包记录 hash 读取
    public function hgetusermoeny($userId, $hbId)
    {
        return $this->redis->hget(self::HASH_FIVE_HB_USER_LOG_INFO . date('Y-m-d') . $hbId, $userId);
    }

    //五元红包 红包列表记录
    public function hgetallmoney($hbId)
    {
        return $this->redis->hgetall(self::HASH_FIVE_HB_USER_LOG_INFO . date('Y-m-d') . $hbId);
    }

    //五元红包 用户头像信息 写入
    public function setusericoninfo($userId, $info)
    {
        $this->redis->setex(self::STRING_FIVE_HB_USER_ICON_INFO.date('Y-m-d') . $userId, 3600, json_encode($info));
    }

    //五元红包 用户头像信息 读取
    public function getusericoninfo($userId)
    {
        return $this->redis->get(self::STRING_FIVE_HB_USER_ICON_INFO.date('Y-m-d') . $userId);
    }

    //五元红包 商品详情二维码路径 写入
    public function setqrcodeinfo($goodsId,$url)
    {
        $this->redis->setex(self::STRING_QR_CODE_GOODS_URL_INFO.$goodsId,86400*90,$url);
    }

    //五元红包 商品详情二维码路径 读取
    public function getqrcodeinfo($goodsId)
    {
       return $this->redis->get(self::STRING_QR_CODE_GOODS_URL_INFO.$goodsId);
    }


    //五元红包 商品详情二维码路径 删除
    public function delqrcodeinfo($goodsId)
    {
        return $this->redis->del(self::STRING_QR_CODE_GOODS_URL_INFO.$goodsId);
    }

    //统一分发 活动注册信息 写入
    public function lpushactivityreg($arr=array())
    {
        return $this->redis->lpush(self::LIST_UNIFY_OUT_ACTIVITY_REG.$arr['activity_type'],json_encode($arr));
    }

    //统一分发 活动注册信息 单个删除 读取
    public function rpopactivityreg($activityId)
    {
       return $this->redis->rpop(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId);
    }

    public function lindexreg($activityId)
    {
      return $this->redis->lindex(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId,0);
    }

    //统一分发 活动注册信息 批量取值 读取
    public function batchpopactivityreg($activityId,$len=20)
    {

        $redisLen = $this->redis->llen(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId);
//        if(empty($redisLen)) return false;

        if ($redisLen <= $len) {
            $res=$this->redis->lrange(self::LIST_UNIFY_OUT_ACTIVITY_REG . $activityId, 0, -1);

        } else {
           $res=$this->redis->lrange(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId,0,$len-1);

        }
        return $res;
    }


    //统一分发 活动注册信息 批量取值 删除
    public function batchlistactivityreg($activityId,$len=20)
    {

        $redisLen = $this->redis->llen(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId);
//        if(empty($redisLen)) return false;

        if ($redisLen <= $len) {
            $res=$this->redis->del(self::LIST_UNIFY_OUT_ACTIVITY_REG . $activityId);

        } else {
            $res=$this->redis->ltrim(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId,$len,-1);

        }
        return $res;
    }

    //统一分发 活动注册信息 获取长度
    public function llenactivityreg($activityId)
    {
     return $this->redis->llen(self::LIST_UNIFY_OUT_ACTIVITY_REG.$activityId);
    }

    //用户每日拆红包统计 每日最多三次 写入
    public function incrusertotalnum($userId)
    {
       $this->redis->incr(self::STRING_USER_DAY_TOTAL_NUM_INFO.$userId.date('Y-m-d'));
    }

    //用户每日拆红包统计 每日最多三次 读取
    public function getincrusertotalnum($userId)
    {
        return $this->redis->get(self::STRING_USER_DAY_TOTAL_NUM_INFO.$userId.date('Y-m-d'));
    }

    //写入时间 用于判断是否有请求
    public function setcountinfo()
    {
        $time=time();
        $this->redis->set(self::STRING_PV_COUNT_INTO,$time);

    }

    //读取时间 用于判断是否有请求
    public function getcountinfo()
    {
        return $this->redis->get(self::STRING_PV_COUNT_INTO);
    }

    //增长域名Key
    public function setdomainnum()
    {
        $this->redis->incr(self::STRING_DOMAIN_NUM_INFO);
    }

    //获取域名key
    public function getdomainnum()
    {
        return $this->redis->get(self::STRING_DOMAIN_NUM_INFO);
    }

    public function writedomain($num)
    {
        $this->redis->set(self::STRING_DOMAIN_NUM_INFO,$num);
    }

    //五元红包  当日红包已经累计拆分金额 写入
    public function setdayincrbyfloatmoney($money)
    {
        $money=sprintf("%.2f",$money);
        $this->redis->INCRBYFLOAT(self::STRING_FIVE_HB_DAY_MONEY_INFO . date('Y-m-d'), $money);
    }

    //五元红包  当日红包已经累计拆分金额 读取
    public function getdayincrbyfloatmoney()
    {
        return sprintf("%.2f",$this->redis->get(self::STRING_FIVE_HB_DAY_MONEY_INFO . date('Y-m-d')));
    }

    //五元红包 赚赚二维码分享参数 写入
    public function setearnshareparams($id,$info)
    {
       $this->redis->setnx(self::STRING_FIVE_EARN_SHARE_PARAMS_INFO.$id,$info);
    }

    //五元红包 赚赚二维码分享参数 读取
    public function getearnshareparams($id)
    {
       return $this->redis->get(self::STRING_FIVE_EARN_SHARE_PARAMS_INFO.$id);
    }
}