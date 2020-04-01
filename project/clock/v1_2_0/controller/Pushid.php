<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-21
 * Time: 21:01
 */
namespace app\v1_2_0\controller;

use think\Controller;
use app\v1_0_0\model\ClockPushLog;
use think\Log;

class Pushid extends Controller{

    public function index()
    {
        try{
            $pushLog = new ClockPushLog();
            $tmpPushLog = $pushLog->where('status', 1)->limit(500)->select();
            $pushTmpIds = [];
            $pushTmpUserIds = [];
            foreach ($tmpPushLog as $t => $m) {
                $pushTmpIds[] = $m->id;
                $pushTmpUserIds[] = $m->user_id;
            }
            //调java接口
            $resInfo = $this->pushIds($pushTmpUserIds);
            $javaInfo=json_decode($resInfo,true);
            if($resInfo!==false&&$javaInfo['code']==1000){
                //更新status状态
                $status = $this->updateStatus($pushTmpIds);
                $pushLog->whereTime('create_time','<',strtotime("-3 days"))
                    ->delete();
                Log::info('在时间:' . date('Y-m-d H:i:s') . '更新表clock_push_log的状态，更新的id集为' . json_encode($pushTmpIds));
            }
            return 'suc';
        }catch (\Exception $exception){
            Log::info('系统推送失败'.date('Y-m-d H:i:s'));
            return 'error';
        }

    }

    //调java接口
    public function pushIds($aIds = array())
    {
        try {
            $pushTime=config('clock_push_time');
            $hms=empty($pushTime)?'11:30:00':$pushTime;
            $triggerTime=strtotime(date('Y-m-d '.$hms));

            $data = [
                'title' => '打卡返现通知',
                'content' => '时间到了！坚持打卡，订单全额返现。',
                'triggerTime' => $triggerTime,
                'userIds' => $aIds,
            ];
            $host = config("DOMAIN_JAVAAPI_TJJ")[10].'/message/clockin';

            Log::info('请求时间为:' . date('Y-m-d H:i:s') . '请求地址为:' . $host . '请求参数为:' . json_encode($data));
            $res = httpPost($host,$data);
            Log::info('JAVA返回结果为' . json_encode($res));
            return $res;
        } catch (\Exception $exception) {
            Log::info('调java接口失败');
            return false;
        }
    }


    //java返回成功 更新clock_push_log 的status
    public function updateStatus($ids)
    {
        try {
            $push = new ClockPushLog();
            $data = [];
            foreach ($ids as $k => $v) {
                $data[$k]['id'] = $v;
                $data[$k]['status'] = 2;
                $data[$k]['create_time'] = time();
            }
            $info = $push->saveAll($data);
            return $info;
        } catch (\Exception $exception) {
            Log::info('批量更新clock_push_log的status状态失败,失败的主键id为:' . json_encode($ids));
            return false;
        }

    }
}