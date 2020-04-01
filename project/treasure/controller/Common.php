<?php
/**
 * 天天夺宝基类
 */
namespace app\treasure\controller;

use think\cache\driver\Redis;
use think\Controller;
use think\Exception;
use app\zz\service\GrpcService;

header("content-type:application/json");
class Common extends Controller
{
    #########################redis属性设置##########################################
    const EXPIRATION = 300; //默认缓存时间5分钟
    const EXPIRATION_ONEHOUR = 3600; //缓存时间1小时
    const EXPIRATION_ONEDAY = 86400;//缓存1天时间
    const EXPIRATION_ONEWEEK = 604800;//缓存1周时间
    public $redis; //设置redis对象

    public function _initialize()
    {
    }

    public function __construct()
    {
        parent::__construct();
        try {
            $this->redis = new Redis(config('redis'));
            $this->handler = $this->redis->handler();
        } catch (Exception $e) {
            $this->apiLog($_REQUEST, $e->getMessage(), $_SERVER);
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
        $data['data'] = isset($data['data']) ? $data['data'] : [];
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

        try {
            $aliyun = new \aliyun\Log(1);
            $aliyun->addDms($content);
        } catch (\think\Exception $e) {
            $e->getMessage();
        }
    }

    //go 验证用户token
    public function goCheckToken($userId,$uuid,$token)
    {
        try{
            $arr=[
                'user_id'=>$userId,
                'uuid'=>$uuid,
                'token'=>$token,
                'app_resource'=>0,
            ];
            $grpcService=new GrpcService();
            $token=$grpcService::goCheckUserToken($arr);
            return $token;
        }catch (\Exception $exception){
            return false;
        }
    }



    //获取批量用户信息
    public function getBatchUserInfo($userIds=array())
    {
        try{
            $grpcService=new GrpcService();
            $userInfo=$grpcService::getbatchUserInfo($userIds);
            return $userInfo;
        }catch (\Exception $exception){
            return false;
        }

    }


    //获取用户天域值 跟是否在黑名单信息
    public function userBlackInfo($userId)
    {
        try{
            $grpcService=new GrpcService();
            $userInfo=$grpcService::getUserRiskAllInfo($userId);
            return $userInfo;
        }catch (\Exception $exception){
            return false;
        }
    }
}