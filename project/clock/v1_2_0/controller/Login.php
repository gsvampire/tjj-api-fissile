<?php
/**
 * Created by PhpStorm.
 * User: shine
 * Date: 2019/1/17
 * Time: 20:37
 */

namespace app\v1_2_0\controller;

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
     */
    public function wxLogin($uuid,$s_user_id='',$os='wap')
    {
        $url=protocol().$_SERVER['HTTP_HOST'].'/clock/view/v1_0_0/index';
        $url.= $s_user_id==''?'':'/s_user_id/'.$s_user_id;
        if(!preg_match_all('/^\d*$/',$s_user_id)){
            Header('location:'.$url.'/wxLoginMessage/paramsError');
        }
        $res=$this->getUnionId();
        $params=[
            'uuid'=>$uuid,
            's_user_id'=>$s_user_id,
            'activity_type'=>2,
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

        $userInfo=api('wap/user/wxLogin',$params,false,config('DOMAIN_API_TJJ_SERVICE'));

        if($userInfo['result']==1){
            $token=$userInfo['token'];
            $user_id=$userInfo['userId'];
            //校验用户信息
            Header('location:'.$url.'/user_id/'.$user_id.'/token/'.$token.'/uuid/'.$uuid.'/wxLoginBack/1');
        }else{
            Header('location:'.$url.'/wxLoginBack/0/wxLoginMessage/'.$userInfo['message']);
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