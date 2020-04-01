<?php
/*
 * 裂变活动基类
 */
namespace app\index\controller;

use think\Controller;
header("content-type:application/json");
header("Cache-Control: no-cache");
class Common extends Controller
{
    public function _initialize()
    {
    }

    public function __construct()
    {
        parent::__construct();
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
    public function interlayer($data,$from_api = false)
    {
        if (!$from_api) {
            $result = $this->result_message($data);
        } else {
            $result = $data;
        }
        echo json_encode($result);
    }

    /*
     * 用户验证
     * @param user_id 用户标识
     * @param uuid 设备号
     * @param token 登录token
     * @param app_resource 默认0
     */
    public function checkToken($user_id,$token,$uuid,$app_resource=0)
    {
        $params=[
            'user_id'=>$user_id,
            'token'=>$token,
            'uuid'=>$uuid,
            'app_resource'=>$app_resource,
        ];

        $host = config("DOMAIN_JAVAAPI_TJJ")[2];
        $res=java_api('user/checkAccessToken',$params,false,$host);
        return $res;
    }

    /*
     * 用户信息
     * @param user_id 用户标识
     * @param uuid 设备号
     * @param token 登录token
     * @param app_resource 默认0
     */
    public function userInfo($user_ids,$fields='nickname,avatar,username')
    {
        $params=[
            'user_ids'=>$user_ids,
            'fields'=>$fields
        ];
        $host = config("DOMAIN_JAVAAPI_TJJ")[2];
        $res=java_api('user/getInfoInBulk',$params,false,$host);
        return $res;
    }

    /*
     * 用户信息
     * @param user_id 用户标识
     * @param uuid 设备号
     * @param token 登录token
     * @param app_resource 默认0
     */
    public function userInfoService($user_ids,$fields='nickname,avatar,username')
    {
        $params=[
            'user_ids'=>$user_ids,
            'fields'=>$fields
        ];
        $host = config("DOMAIN_API_TJJ_SERVICE");
        $res=api('wap/user/getInfoInBulk',$params,false,$host);
        return $res;
    }

    //错误返回结果
    public function returnError($code,$data='')
    {
        exit(json_encode(array_combine(array('result','message','data') , array($code, config('message')[$code],$data)))) ;
    }
    //正确结果返回
    public function returnSuccess($code = 1, $data)
    {
        $res = [
            'result' => $code ,
            'message' =>config('message')[$code],
            'data' => $data,
        ];
        exit(json_encode($res)) ;
    }

    public function apiLog($params, $message, $data=[] ,$logLevel = 3)
    {
        $content[]=[
            'apiType'=>2,
            'url'=>$_SERVER['REQUEST_URI'],
            'params'=>json_encode($params),
            'data'=>$data==[]?'':json_encode($data),
            'message'=>$message,
            'logLevel'=>$logLevel,
            'clientIp'=>getIp(),
            'logTime'=>time(),

        ];
        try{
            $aliyun=new \aliyun\Log(1);
            $aliyun->addDms($content);
        }catch(Exception $e){
            $e->getMessage();
        }

    }


}