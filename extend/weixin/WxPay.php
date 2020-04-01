<?php
/**
 * Created by PhpStorm.
 * User: shine
 * Date: 2019/3/21
 * Time: 17:29
 */

namespace weixin;

class WxPay
{
    public $appId;
    public $appSecret;
    public $mchId;
    private $access_token;
    private $baseData;

    public $postData=[];//post数据  默认get
    public $ip;
    public $key;
    public $cert;
    public $sign_type='MD5';


    public static function Install(){
        return new self();
    }

    public  function __construct($company)
    {
        $this->appId = config($company)['APPID'];
        $this->appSecret = config($company)['APPSECRET'];
        $this->mchId = config($company)['MCHID'];
        $this->key = config($company)['KEY'];
        $this->notify_url = config($company)['NOTIFY_URL'];
//        $this->openId=$this->getOpenId();

        $this->ip=$this->get_remote_ip();
        $this->baseData=[
            'appid'=>$this->appId,
            'mch_id'=>$this->mchId,
            'nonce_str'=>$this->getNonceStr(),
//            'sign_type'=>$this->sign_type
        ];
    }


    public function httpRequest($url)
    {
        //初始化
        $curl = curl_init();
        //设置curl传输选项
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);

        if($this->cert == true){
            //设置证书  绝对路径
            curl_setopt($curl,CURLOPT_SSLCERTTYPE,'PEM');
            //curl_setopt($curl,CURLOPT_SSLCERT, dirname(__FILE__).'/'.WxPayConf::SSLCERT_PATH);
            curl_setopt($curl,CURLOPT_SSLKEYTYPE,'PEM');
            //curl_setopt($curl,CURLOPT_SSLKEY, dirname(__FILE__).'/'.WxPayConf::SSLKEY_PATH);
        }

        if ( !empty($this->xmlData) ) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $this->xmlData);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $res = curl_exec($curl);
        $error = curl_errno($curl);
        curl_close($curl);
        return $res;
    }

    private function get_remote_ip() {
        if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
            $ip = $_SERVER["HTTP_CLIENT_IP"];
        } else if (!empty($_SERVER["HTTP_X_REAL_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_REAL_FORWARDED_FOR"];
        } else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
            $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
        } else if (!empty($_SERVER["REMOTE_ADDR"])) {
            $ip = $_SERVER["REMOTE_ADDR"];
        } else {
            $ip = '';
        }
        $ip=explode(',',$ip);
        return $ip[0];
    }

    public function getNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    //获取签名
    public function getSign($arrData)
    {
        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        ksort($arrData);
        $string = "";
        foreach ($arrData as $k => $v)
        {
            if($v != "" && !is_array($v)) {
                $string.=$k."=".$v."&";
            }
        }
        $stringSignTemp=$string.'key='.$this->key ;
        if($this->sign_type=='HMAC-SHA256'){
            $sign=strtoupper(hash_hmac("sha256",$stringSignTemp,$this->key));
        }else{
            $sign=strtoupper(MD5($stringSignTemp));
        }
        return $sign;
    }

    /**
     * 获取获取open_id
     */
    public function getOpenId()
    {
        if (!isset($_GET['code'])) {
            $redirect_uri=urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$_SERVER['QUERY_STRING']);
            $codeUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$this->appId&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
            Header("Location: $codeUrl");
            exit();
        }else{
            $code=$_GET['code'];
            $url="https://api.weixin.qq.com/sns/oauth2/access_token?appid=$this->appId&secret=$this->appSecret&code=$code&grant_type=authorization_code";
            $data=json_decode($this->httpRequest($url),true);
            $this->openId=$data['openid'];
            $this->access_token=$data['access_token'];
            return $this->openId;
        }

    }

    public function doUrl($url)
    {
        $this->toXml();
        switch ($url)
        {
            case 'refreshToken':$url = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=$this->appId&grant_type=refresh_token&refresh_token=$this->refresh_token";break;
            case 'userInfo':$url = "https://api.weixin.qq.com/sns/userinfo?access_token=$this->access_token&openid=$this->openId&lang=zh_CN";break;
            case 'unifiedorder':$url='https://api.mch.weixin.qq.com/pay/unifiedorder';break;
            case 'orderquery':$url='https://api.mch.weixin.qq.com/pay/orderquery';break;
            case 'closeorder':$url='https://api.mch.weixin.qq.com/pay/closeorder';break;
            case 'refund':$url='https://api.mch.weixin.qq.com/secapi/pay/refund';break;//退款
            case 'refundquery':$url='https://api.mch.weixin.qq.com/pay/refundquery';break;
            case 'downloadbill':$url='https://api.mch.weixin.qq.com/pay/downloadbill';$toArray=true;break;
            case 'downloadfundflow':$url='https://api.mch.weixin.qq.com/pay/downloadfundflow';$toArray=true;break;
            case 'micropay':$url='https://api.mch.weixin.qq.com/pay/micropay';break;
            case 'reverse':$url='https://api.mch.weixin.qq.com/secapi/pay/reverse';break;
        }
        $res=$this->httpRequest($url);
        if(isset($toArray)){return $res;}
        $result=$this->toArray($res);
        return $result;
    }

    /**
     * 获取code
     * 静默授权 scope=snsapi_base
     */
    public function getCode()
    {
        $this->redirect_uri=urlencode('http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$_SERVER['QUERY_STRING']);
        $codeUrl = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$this->appId&redirect_uri=$this->redirect_uri&response_type=code&scope=$this->scope&state=123#wechat_redirect";
        Header('Location:' . $codeUrl);
        exit();
    }

    /**
     * 输出xml字符
     */
    public function toXml()
    {
        $arrData=array_merge($this->postData,$this->baseData);
        $sign=$this->getSign($arrData);
        $arrData['sign']=$sign;
        $xml = "<xml>";
        foreach ($arrData as $key=>$val)
        {
            if (is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
            }
        }
        $xml.="</xml>";
        $this->xmlData=$xml;
    }

    /**
     * 将xml转为array
     */
    public function toArray($xml)
    {
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }


}