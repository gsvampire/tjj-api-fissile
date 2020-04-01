<?php
/**
 * 裂变活动基类
 */
namespace app\anniversary\controller;

use think\cache\driver\Redis;
use think\Controller;
use think\Exception;

header("content-type:application/json");
class Common extends Controller
{
    #########################redis属性设置##########################################
    const EXPIRATION = 300; //默认缓存时间5分钟
    const EXPIRATION_ONEHOUR = 3600; //缓存时间1小时
    const EXPIRATION_ONEDAY = 86400;//缓存1天时间
    const EXPIRATION_ONEWEEK = 604800;//缓存1周时间
    public $redis; //设置redis对象

    //滚动条单次请求条数
    const NUM = 20;

    public function _initialize()
    {
    }

    public function __construct()
    {
        parent::__construct();
        try{
            $this->redis = new Redis(config('redis'));
            $this->handler = $this->redis->handler();
        }catch(Exception $e){
            $this->apiLog($_REQUEST,$e->getMessage(),$_SERVER);
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] == 'OPTIONS')) {
            exit;
        }
    }

    /**
     * 参数验证
     */
    public function filter($request)
    {
        $filter = config('FILTER');
        $param = urldecode(http_build_query($request));
        foreach ($filter as $k => $v) {
            if (strstr($param, $v)) {
                echo "参数有误！";
                die;
            }
        }
    }

    /**
     * 返回message
     * @param $data
     * @return mixed
     */
    public function result_message($data)
    {
        $data['message'] = config('message')[$data['result']];
        return $data;
    }

    /**
     * 数据返回结构
     * @param $data
     * @param bool $from_api
     */
    public function interlayer($data, $from_api = false)
    {
        if (!$from_api) {
            $result = $this->result_message($data);
        } else {
            $result = $data;
        }
        echo json_encode($result);
        die;
    }

    /*
     * 用户验证
     * @param user_id 用户标识
     * @param uuid 设备号
     * @param token 登录token
     * @param app_resource 默认0
     */
    public function checkToken($user_id, $token, $uuid, $app_resource = 0)
    {
        $params = [
            'user_id' => $user_id,
            'token' => $token,
            'uuid' => $uuid,
            'app_resource' => $app_resource,
        ];

        $host = config("DOMAIN_JAVAAPI_TJJ")[2];
        $res = java_api('user/checkAccessToken', $params, false, $host);
        return $res;
    }

    /*
     * 用户信息
     * @param user_id 用户标识
     * @param uuid 设备号
     * @param token 登录token
     * @param app_resource 默认0
     */
    public function userInfo($user_ids, $fields = 'nickname,avatar,username')
    {
        $params = [
            'user_ids' => $user_ids,
            'fields' => $fields
        ];
        $host = config("DOMAIN_JAVAAPI_TJJ")[2];
        $res = java_api('user/getInfoInBulk', $params, false, $host);
        $count = (!empty($res['users'])) ? count($res['users']) : 0;
        if ($res['result'] != 1 || $count == 0) {
            $this->returnError(-2);
        }
        for ($i = 0; $i < $count; $i++) {
            $username = $res['users'][$i]['username'];
            $nickname = $res['users'][$i]['nickname'];
            $res['users'][$i]['username'] = $username == '' ? $username : substr_replace($username, 'xxxx', 3, 4);
            $res['users'][$i]['nickname'] = $nickname == '' ? $res['users'][$i]['username'] : $nickname;
        }
        return $res['users'];
    }

    /*
     * 用户信息
     * @param user_id 用户标识
     * @param uuid 设备号
     * @param token 登录token
     * @param app_resource 默认0
     */
    public function userInfoService($user_ids, $fields = 'nickname,avatar,username')
    {
        $params = [
            'user_ids' => $user_ids,
            'fields' => $fields
        ];
        $host = config("DOMAIN_API_TJJ_SERVICE");
        $res = api('wap/user/getInfoInBulk', $params, false, $host);
        return $res;
    }

    //错误返回结果
    public function returnError($code, $data = '')
    {
        exit(json_encode(array_combine(array('result', 'message', 'data'), array($code, config('message')[$code], $data))));
    }

    //正确结果返回
    public function returnSuccess($code = 1, $data = [])
    {
        $res = [
            'result' => $code,
            'message' => config('message')[$code],
            'data' => $data,
        ];
        exit(json_encode($res));
    }

    /**
     * 优惠券信息
     * @param $coupon_id
     * @param $user_id
     * @param $uuid
     * @param $token
     * @return mixed
     */
    public function coupon_info($coupon_id, $user_id, $uuid, $token)
    {
        $params_coupon = array(
            'user_id' => $user_id,
            'token' => $token,
            'uuid' => $uuid,
            'couponId' => $coupon_id,
        );
        $coupon_info = api('wap/Coupon/getPlatformCoupon', $params_coupon);
        return $coupon_info;
    }

    /**
     * 领取优惠券
     * @param $coupon_id
     * @param $type 1：平台券，2：商品券
     * @param int $result
     * @param $user_id
     * @param $uuid
     * @param $token
     * @return mixed
     */
    public function get_coupon($coupon_id, $type, $result = 0, $user_id, $uuid, $token)
    {
        $params = [
            'user_id' => $user_id,
            'token' => $token,
            'uuid' => $uuid,
        ];
        if ($type == 2) {//商品券
            $params['coupon_id'] = "storeCoupon-1-".$coupon_id;
            $getCoupon = api('Wap/coupon/receiveCoupon', $params);
        } else {//平台券
            $params['couponString'] = $coupon_id;
            $params['is_post'] = 1;
            $params['result'] = $result;
            $getCoupon = api('Wap/Coupon/receiveFullCoupon', $params);
        }
        return $getCoupon;
    }

    /*
     * 清除指定key  string类型
     * @params $keys array
     */
    public function resetKey($keys)
    {
        //$this->redis = new Redis(config('redis'));
        $count = count($keys);
        for ($i = 0; $i < $count; $i++) {
            $this->redis->set($keys[$i], null);
        }
    }

    public function checkUser($user_id, $token, $uuid)
    {
        $user = $this->checkToken($user_id, $token, $uuid);
        if (!isset($user['result']) || $user['result'] != 1) {
            $this->returnError(-2, isset($user['message']) ? $user['message'] : '');
        }
    }

    public function header401()
    {
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="My Realm"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'official activity test!';
            exit;
        }
    }

    /*
     * 删除redis
     * @param key 键名(模糊匹配末尾加*)
     */
    public function deleteRedis401($key)
    {
        $this->header401();

        if ($_SERVER['PHP_AUTH_USER'] == 'TJJDELETE' && $_SERVER['PHP_AUTH_PW'] == 'TJJ123456') {
            //$this->redis = new Redis(config('redis'));
            $this->redis->rm($key);
        } else {
            header('WWW-Authenticate: Basic realm="Auth!"');
            header('HTTP/1.0 401 Unauthorized');
        }

    }

    /*
     * 添加集合成员
     */
    public function addMem($key, $value, $expire)
    {
        $this->handler->expire($key, $expire);
        $this->handler->sadd($key, $value);
    }

    /*
     * 判断集合成员是否已存在
     */
    public function existMem($key, $value)
    {
        return $this->handler->sismember($key, $value);
    }

    /*
     * 查看集合成员
     */
    public function showMem($key)
    {
        return $this->handler->smembers($key);
    }


    /*
     * 查询redis
     * @param key 键名
     * @param type 类型(0,默认读取string 1,读取set类型)
     */
    public function checkRedis401($key, $type = 0)
    {
        $this->header401();
        if ($_SERVER['PHP_AUTH_USER'] == 'TJJ' && $_SERVER['PHP_AUTH_PW'] == 'TJJ123456') {
            switch ($type) {
                case 1:
                    echo json_encode($this->showMem($key));
                    break;
                case 0:
                    echo empty($this->redis->get($key)) ? '此键无对应Redis记录' : json_encode($this->redis->get($key));
                    break;
                case 2:
                    echo empty($this->handler->hgetall($key)) ? '此键无对应Redis记录' : json_encode($this->redis->handler()->hgetall($key));
                    break;
            }
        } else {
            header('WWW-Authenticate: Basic realm="Auth!"');
            header('HTTP/1.0 401 Unauthorized');
        }

    }

    public function commParams()
    {
        $content = file_get_contents('php://input');
        $content = json_decode($content, true);
        return $content;
    }

    public function blackList($user_id)
    {
        //黑名单
        try{
            $redisT = new Redis(config('blackRedis'));
            $handler = $redisT->handler();
            $res = $handler->exists('zsyyblklist_' . $user_id);
        }catch(Exception $e){
            $res = 0;
            $this->apiLog(['path'],$e->getMessage());
        }
        return $res;
    }

    public function isBlack($user_id)
    {
        $res = $this->blackList($user_id);
        if ($res) {
            $this->returnError(-18, 'blackList');
        }
    }

    public function couponIds($coupon_id)
    {
        $str = 'fullCoupon~';
        if (strchr($coupon_id, ',')) {
            $arr = explode(',', $coupon_id);
            $count = count($arr);
            for ($i = 0; $i < $count; $i++) {
                $arr[$i] = $str . $arr[$i];
            }
            $coupon_id = implode(',', $arr);
        } else {
            $coupon_id = $str . $coupon_id;
        }
        return $coupon_id;
    }


    /*
     * 领取平台优惠券  内网接口  可重复领取
     */
    public function getPlatformCoupon($user_id, $coupon_id)
    {
        $params = [
            'user_id' => $user_id,
            'stringCoupon' => $coupon_id,
            'result' => 1,
            'is_post' => 1
        ];
        $host = config('DOMAIN_API_TJJ_SERVICE');
        $getCoupon = api('Wap/Coupon/receiveFullCoupon', $params, false, $host);
        return $getCoupon;
    }

    /*
     * 查询优惠券列表
     */
    public function getCouponList($coupon_id)
    {
        $params = [
            'couponString' => $coupon_id,
            'is_post' => 1
        ];
        $getCouponList = api('Wap/Coupon/couponList', $params);
        $couponList = isset($getCouponList['data']) ? $getCouponList['data'] : [];
        return $couponList;
    }

    /**
     * 商品券列表获取
     * @param $coupon_id
     * @param $goods_id
     * @param $user_id
     * @param $uuid
     * @param $token
     * @param $coordinate
     * @return mixed
     */
    public function goodsCouponList($coupon_id, $goods_id, $user_id, $uuid, $token,$coordinate)
    {
        $params = [
            'user_id' => $user_id,
            'uuid' => $uuid,
            'token' => $token,
            'coupon_id' => $coupon_id,
            'goods_id' => $goods_id,
            'type' => $coordinate,
            'is_post' => 1
        ];
        $getCouponList = api('wap/coupon/getBatchCoupon', $params);
        return $getCouponList;
    }

    public function apiLog($params, $message, $data = [], $logLevel = 3)
    {
        $content[] = [
            'apiType' => 2,
            'url' => $_SERVER['REQUEST_URI'],
            'params' => json_encode($params),
            'data' => $data == [] ? '' : json_encode($data),
            'message' => $message,
            'logLevel' => $logLevel,
            'clientIp' => getIp(),
            'logTime' => time(),
        ];

        try{
            $aliyun = new \aliyun\Log(1);
            $aliyun->addDms($content);
        }catch(\think\Exception $e){
            $e->getMessage();
        }
    }

    public function intFilter($params)
    {
        foreach ($params as $k => $v) {
            if(!preg_match('/^\d+$/',$v)){
                return false;
            }
        }
        return true;
    }
}