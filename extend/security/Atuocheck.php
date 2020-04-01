<?php
/**
 * Created by PhpStorm.
 * User: Thinkpad
 * Date: 2019/4/26
 * Time: 13:56
 */
namespace security;
use phpmailer\phpmailer;
use think\cache\driver\Redis;

class Atuocheck{
    private $uri; //待检测网页链接
    private $start_time; //检测开始时间设置  毫秒
    private $redis ; //redis 初始化
    const SAFE='safety_log';
    public function __construct()
    {
        //TODO：初始化相关操作，如redis
        $this->redis = new Redis(config('redis'));
    }

    /*
     * return @ array("code"=> value,"content" => value）
     */
    private function autoCurl($url){
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 10);
        $content = curl_exec($handle);
        $code = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $data=[
            'code'=>$code,
            'content'=>$content,
        ];
        return $data;
    }

    /*
     * 分析js,处理数据
     */
    private function analysis($url){
        //TODO:通过content正则匹配script内容和错误，然后入库
        //记录：执行时间  js毫秒-php毫秒  $server['req_time'],
        $data=$this->autoCurl($url);
        if($data['code']==200){
            $data['content']='<script id="tjjFissileErrorObj" type="text\/javascript">tjjFissileBuger={"title":"免单购物团","userAgent":"Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Mobile Safari/537.36","errorCode":"200","endTime":1556429617038,"os":"Android","networkStr":"other","errorArr":[]}</script>';

            preg_match("/<script\s*?id=\"tjjFissileErrorObj\"\s*.*?>tjjFissileBuger=(.*\s*?)<\/script>/is",$data['content'],$match1);

            if(empty($match1)){
                return false;
                //TODO：记录错误  发送通知
            }
            $match= json_decode($match1[1],true);
            //dump($data['code']);die;
            if($match['errorCode']==200){
                //请求时间毫秒
                $_SERVER['REQUEST_TIME_FLOAT']=1556429564.228;

                //操作数据库
                $addData=[
                    'title'=>$match['title'],
                    'os'=>$match['os'],
                    'errorCode'=>$match['errorCode'],
                    'errorArr'=>$match['errorArr'],
                    'webCode'=>$data['code'],
                    'responseTime'=>$match['endTime']-$_SERVER['REQUEST_TIME_FLOAT']*1000,
                ];

            }else{

            }



            return $match;

        }else{
            //TODO：记录错误  发送通知  邮件
            return false;
        }
    }

    //TODO:执行
    public function exec($url){
        return $this->analysis($url);
    }

    //TODO:outprint
    public function outprint(){

    }


}