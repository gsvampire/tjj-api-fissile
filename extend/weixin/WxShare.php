<?php
/**
 * Created by PhpStorm.
 * User: shine
 * Date: 2019/01/17
 * Time: 17:29
 */

namespace weixin;


use think\cache\driver\Redis;

class WxShare
{
    private $appId;
    private $appSecret;
    public $data=null;//post数据
    public $url;
    public $openId;
    public $accessToken;
    public $sign_type='MD5';
    public $string;
    public $scope='snsapi_userinfo';//snsapi_base  snsapi_userinfo
    public $redis;

    //redis
    const TICKET='wap_wxShare_ticket_newYear';
    const TOKEN='wap_wxShare_token_newYear';




    public static function Install(){
        return new self();
    }

    public  function __construct($appId,$appSecret,$url='')
    {
        //淘集集
        $this->appId = $appId;
        $this->appSecret = $appSecret;
        $this->url = $url;
        $this->redis=new Redis(config('redis'));

    }


    private function httpRequest($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if ( !empty($this->data) ) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $error=curl_error($curl);
        $res = curl_exec($curl);

        curl_close($curl);
        return $res;
    }

    private function getToken()
    {
        $tokenKey = $this::TOKEN.':'.$this->appId;
        $token=$this->redis->get($tokenKey);
        if($token==''){
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appId&secret=$this->appSecret";
            $res=$this->httpRequest($url);
            $token=json_decode($res,true)['access_token'];
            //缓存两个小时
            if($token){
                $this->redis->set($tokenKey,$token,7000);
            }
        }
        return $token;
    }

    private function getTicket() {

        $ticketKey = $this::TICKET.':'.$this->appId;
        $ticket=$this->redis->get($ticketKey);
        if ($ticket == '') {
            $accessToken = $this->getToken();
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=$accessToken&type=jsapi";
            $res = $this->httpRequest($url);
            //echo json_encode($res);
            $ticket = json_decode($res,true)['ticket'];
            if ($ticket) {
                $this->redis->set($ticketKey, $ticket,7000);
            }
        }
        return $ticket;
    }

    private function getNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    

    /**
     * 获取获取open_id
     */
    public function getOpenId()
    {
        if (!isset($_GET['code'])) {
            $redirect_uri=urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$_SERVER['QUERY_STRING']);
            $codeUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$this->appId&redirect_uri=$redirect_uri&response_type=code&scope=$this->scope&state=123#wechat_redirect";
            Header("Location: $codeUrl");
            exit();
        }else{
            $code=$_GET['code'];
            $url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=$this->appId&secret=$this->appSecret&code=$code&grant_type=authorization_code";
            $data=json_decode($this->httpRequest($url),true);
            $this->openId=$data['openid'];
            $this->accessToken=$data['access_token'];
            //return $this->openId;
        }

    }

    //基础配置
    public function getConf()
    {
        $ticket=$this->getTicket();
        $nonceStr=$this->getNonceStr();
        $timestamp=time();
        $string = "jsapi_ticket=$ticket&noncestr=$nonceStr&timestamp=$timestamp&url=$this->url";
        $sign = sha1($string);
        $config=[
            'appId'=>$this->appId,
            'timestamp'=>$timestamp ,
            'nonceStr'=> $nonceStr,
            'signature'=>$sign,
            'jsApiList'=> ['onMenuShareAppMessage','onMenuShareTimeline','onMenuShareQQ','onMenuShareWeibo','onMenuShareQZone','chooseWXPay','updateAppMessageShareData','updateTimelineShareData','hideMenuItems','showMenuItems']
        ];
        return $config;
    }


    public function getUnionId()
    {
        $this->getOpenId();
        $url="https://api.weixin.qq.com/sns/userinfo?access_token=$this->accessToken&openid=$this->openId&lang=zh_CN";
        $data=json_decode($this->httpRequest($url),true);
        return $data;
    }


}