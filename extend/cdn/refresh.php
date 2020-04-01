<?php
namespace cdn;
use aliyun\Log;

/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/6/17
 * Time: 15:22
 */
class refresh
{

    public $ws='https://open.chinanetcenter.com/ccm/purge/ItemIdReceiver';
    public $al='https://cdn.aliyuncs.com';
    public $tx='https://cdn.api.qcloud.com/v2/index.php';


    public function sendReq($url,$params,$method=0,$header=[])
    {
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
        if($method){
            curl_setopt($handle, CURLOPT_POST, 1);
            curl_setopt($handle, CURLOPT_POSTFIELDS, $params);
        }
        if($header){
            curl_setopt($handle, CURLOPT_HTTPHEADER,$header);
        }
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, 0); // 对认证证书来源的检查
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, 0); // 从证书中检查SSL加密算法是否存在
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
        $res = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $curlErr=curl_errno($handle);

        //日志上报
        $content=[
            [
               'apiType'=>4,
               'curlErrNo'=>isset(config('CURL')[$curlErr])?config('CURL')[$curlErr]:$curlErr.' not found',
               'curlCode'=>$code,
               'url'=>$url,
               'apiUrl'=>$url,
               'params'=>json_encode($params),
               'data'=>$res,
               'logLevel'=>$curlErr==0?1:3,
               'clientIp'=>$this->getIp(),
            ]
        ];
        $log=new Log(1);
        $log->addDms($content);

        return json_decode($res,true);
    }

    public function getIp() {
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

    //签名加密
    private function signature($data,$key){
        $sign=base64_encode(hash_hmac("sha1",$data,$key,true));
        return $sign;
    }

    public function getNonceStr($chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789",$length = 16) {
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    public function wangsu($body)
    {
        $date=gmdate('D, d M Y H:i:s').' GMT';//格式需符合RFC 1123规范，如Thu, 17 May 2012 19:37:58 GMT

        $password=$this->signature($date,config('wangsuCdn')['accessKey']);
        $auth=base64_encode(config('wangsuCdn')['accessKeyId'].':'.$password);
        $body=json_encode($body);

        $header = array(
            'method' => 'POST',
            'header' =>
                "Content-type:application/json\r\n".
                "Accept: application/json\r\n".
                "Date: $date\r\n".
                "Authorization: Basic ".$auth
        );
        $refresh=$this->sendReq($this->ws,$body,1,$header);
        return $refresh;
    }

    public function setData($data)
    {
        $public=[
            'Timestamp'=>time(),
            'Nonce'=>time().$this->getNonceStr('0123456789','5'),
            'SecretId'=>config('tengxunCdn')['accessKeyId'],
        ];
        if($data['Action']=='GetCdnRefreshLog'){
            $public['startDate']=date('Y-m-d H:i:s',strtotime("-1 day"));
            $public['endDate']=date('Y-m-d H:i:s');
        }
        $public=array_merge($public,$data);

        ksort($public);
        return $public;
    }



    public function tengxun($data)
    {
        $public=$this->setData($data);
        $string ='';
        foreach ($public as $k => $v) {
            $string .=  str_replace("_",".",$k) . "=" . $v."&";
        }
        $string=rtrim($string,"&");
        $string='POSTcdn.api.qcloud.com/v2/index.php?'.$string;
        $public['Signature']=$this->signature($string,config('tengxunCdn')['accessKey']);
        $refresh=$this->sendReq($this->tx,$public,1);
        return $refresh;
    }

    public function aliData($Object)
    {
        $time=gmdate('Y-m-d').'T'.gmdate('H:i:s').'Z';
        $public=[
            'Format'=>'JSON',
            'Version'=>'2018-05-10',
            'AccessKeyId'=>config('aliyunCdn')['accessKeyId'],
            'SignatureMethod'=>'HMAC-SHA1',
            'Timestamp'=>$time,
            'SignatureVersion'=>'1.0',
            'SignatureNonce'=>$this->getNonceStr(),
            'Action'=>'RefreshObjectCaches',
            'ObjectPath'=>$Object['ObjectPath'],//多个URL之间需要用换行符\n或\r\n分隔
            'ObjectType'=>$Object['ObjectType'],
        ];
        ksort($public);
        return $public;
    }

    public function aliyun($Object)
    {
        $public=$this->aliData($Object);

        $string='';
        foreach ($public as $k => $v) {
            $string .= $k=='Timestamp' || $k=='ObjectPath'?$k . "=" . urlencode($v)."&": $k . "=" . $v."&";
        }
        $string='POST&'.urlencode('/').'&'.urlencode(rtrim($string,"&"));
        $public['Signature']=$this->signature($string,config('aliyunCdn')['accessKey'].'&');
        $refresh=$this->sendReq($this->al,$public,1);
        return $refresh;
    }
}