<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/8/15
 * Time: 15:07
 */

namespace app\general\controller;

use weixin\WxShare;
class Login extends Common
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getUnionId()
    {
        $share=new WxShare(config('wxTjj')['APPID'],config('wxTjj')['APPSECRET']);
        $data=$share->getUnionId();
        return $data;
    }

    /*
     * 微信H5登录
     * @param uuid 设备号(前端生成)
     * @param s_user_id 分享者用户ID
     * @param os 操作系统
     * @param activity_type 活动类型(0:年货免费送)
     * @param lurl 回调链接
     */
    public function wxLogin($uuid,$annual_id='',$s_user_id='',$activity_type=0,$lurl,$os='h5')
    {
        $url=urldecode(urldecode($lurl));
        //$url.=stripos($url,'?')===false?'?':'&';

        $res=$this->getUnionId();
        $params=[
            'uuid'=>$uuid,
//            's_user_id'=>$s_user_id,
//            'activity_type'=>$activity_type,
            'account'=>$res['unionid'],
            'other_login'=>4,
            'user_name'=>'',
            'nickname'=>$res['nickname'],
            'gender'=>$res['sex'],
            'avatar'=>$res['headimgurl'],
            'openid'=>$res['openid'],
            'os'=>$os,
            'source'=>'',
            'version'=>'',
            'app_resource'=>0,
            'client_ip'=>getIp(),
            'referrer_phone '=>'',
            'is_post'=>1
        ];
        if(!$activity_type){
            $this->parseInt($annual_id,$url);
            $params['annual_id']=$annual_id;
        }else{
            $this->parseInt($s_user_id,$url);
            $params['activity_type']=$activity_type;
            $params['s_user_id']=$s_user_id;
        }

        $userInfo=api('wap/user/wxLogin',$params,false,config('DOMAIN_API_TJJ_SERVICE'));
        $url.=$userInfo['result']==1?'/wxLoginBack/1/isNewUser/'.$userInfo['isNewUser'].'/user_id/'.$userInfo['userId'].'/token/'.$userInfo['token'].'/uuid/'.$uuid:'/wxLoginBack/0/wxLoginMessage/'.$userInfo['message'];
        Header('location:'.$url);
    }

    /*
     * 验证参数
     */
    public function parseInt($param,$url)
    {
        if(!preg_match_all('/^\d*$/',$param) || empty($param)){
            Header('location:'.$url.'/wxLoginBack/0&wxLoginMessage/paramsError');
        }
    }

    /*
     * 微信分享配置
     */
    public function shareConf($url)
    {
        $share = new WxShare(config('wxTjj')['APPID'],config('wxTjj')['APPSECRET'],$url);
        $conf = $share->getConf();
        echo json_encode($conf);
    }
}