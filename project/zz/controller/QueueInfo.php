<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-24
 * Time: 15:16
 */

namespace app\zz\controller;

use think\Controller;
use app\zz\service\ZActivity;
use think\Log;
use think\Request;

class QueueInfo extends Controller{

     protected $server;

     public function __construct(Request $request = null)
     {
         if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
             exit;
         }
         parent::__construct($request);
         $this->server=new ZActivity();
     }

     //定时更改状态
    public function addHbMoneyInfo()
     {
        $server=$this->server->getusernomoneyinfo();
        if(empty($server)){
            Log::info('在时间：'.date('Y-m-d H:i:s').'没有要执行的数据');
            return returnJson([],'没有要执行的数据',1);
        }

       $host=config('DOMAIN_PHP_MAIN');
       $url=$host.'/Api2_5_0/activity/giveTransferBalance';
       $param=[
           'userId'=>$server['share_user_id'],
           'giveAmount'=>$server['split_money'],
           'activityType'=>'15',//五元红包活动标示
       ];
       $res=httpPost($url,$param,'text');
       $resInfo=json_decode($res,true);
       $times=date('Y-m-d H:i:s');
       if($resInfo['result']==1){
         //更新表状态
          $info=$this->server->updatestatus($param['id']);
          if(!empty($info)){
              Log::info('定时脚本在时间:'.$times.'发放金额成功,更新表状态成功'.'发放的数据为:'.json_encode($param).'返回结果为:'.$res);
              return returnJson([],'请求成功',1);
          }
           Log::info('定时脚本在时间:'.$times.'发放金额成功,更新表状态失败'.'发放的数据为:'.json_encode($param).'返回结果为:'.$res);
           return returnJson([],'请求成功',1);
       }else{
           Log::info('定时脚本在时间:'.$times.'发放金额失败'.'发放的数据为:'.json_encode($param).'返回结果为:'.$res);
           return returnJson([],'请求失败',-1);
       }

     }

}