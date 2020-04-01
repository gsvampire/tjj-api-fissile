<?php
/*
 * 裂变活动基类
 */
namespace app\zhuanzhuan\controller;

use think\Controller;
header("content-type:application/json");
class Common extends Controller
{
    public function _initialize()
    {
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
        echo json_encode($result);die;
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
        $count=(!empty($res['users'])) ? count($res['users']) : 0;
        if($res['result']!=1 || $count==0){$this->returnError(-2);}
        for($i=0;$i<$count;$i++){
            $username=$res['users'][$i]['username'];
            $nickname=$res['users'][$i]['nickname'];
            $res['users'][$i]['username']=$username==''?$username:substr_replace($username,'xxxx',3,4);
            $res['users'][$i]['nickname']=$nickname==''?$res['users'][$i]['username']:$nickname;
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
    public function returnSuccess($code = 1, $data=[])
    {
        $res = [
            'result' => $code ,
            'message' =>config('message')[$code],
            'data' => $data,
        ];
        exit(json_encode($res)) ;
    }

}