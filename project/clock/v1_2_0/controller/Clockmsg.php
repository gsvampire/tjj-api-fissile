<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-03-20
 * Time: 20:23
 */

namespace app\v1_2_0\controller;

use think\Controller;
use app\v1_0_0\model\ClockPayLog;
use app\v1_0_0\model\ClockPunchCard;
use app\v1_0_0\model\ClockPushLog;
use think\Log;

class Clockmsg extends Controller
{
    //打卡状态  1位打卡中
    const CLOCK_STATUS = 1;

    protected $limit=500;
    public function index()
    {

        try {
            //当前时间小于配置时间 退出
            $nowTime = time();

            //判断当前时间与活动时间相差的天数
            $payLog = new ClockPayLog();
            $cardLog = new ClockPunchCard();
            $aUserIds = [];

            //查询下订单时间与当前时间相比 3天内的数据
            $aPayUid=$payLog->where('status', self::CLOCK_STATUS)
                ->column('user_id');

            $aUserIds = $aPayUid;

            if (empty($aUserIds)) {
                return '没有要推送的用户';
            }
           Log::info('需要执行的总数为：'.count($aUserIds));
            //插入到clock_push_log
            $count=ceil(count($aUserIds)/$this->limit);
            Log::info('需要执行的次数'.$count);
            for ($i=1;$i<=$count;$i++){
                Log::info('第 '.$i. ' 几次执行');
                $offset=($i-1)*($this->limit);
                $ids=array_slice($aUserIds,$offset,$this->limit);
                $saveInfo=$this->saveIds($ids);
                if($saveInfo===false){
                    Log::info('更新到clock_push_log表失败'.date('Y-m-d H:i:s'));
                    return '更新到clock_push_log表失败';
                }
            }

            return 'suc';

        } catch (\Exception $exception) {
            Log::info('系统发现异常,时间为：' . date('Y-m-d H:i:s'));
            return 'error';
        }


    }


    /**
     * @param $a
     * @param $b
     * @return float
     * 判断两个日期相差的天数
     */
    public function count_days($a, $b)
    {
        $days = abs($a - $b) / 3600 / 24;
        $countDays = number_format($days, 2);
        return $countDays;
    }


    //调java接口
    public function pushIds($aIds = array())
    {
        try {
            $data = [
                'title' => '打卡返现通知',
                'content' => '时间到了！坚持打卡，订单全额返现。',
                'triggerTime' => strtotime(config('clock_push_time')),
                'userIds' => $aIds,
            ];
            $host = config("DOMAIN_JAVAAPI_TJJ")[10] . '/message/clockin';

            Log::info('请求时间为:' . date('Y-m-d H:i:s') . '请求地址为:' . $host . '请求参数为:' . json_encode($data));
            $res = httpPost($host, $data);
            Log::info('JAVA返回结果为' . json_encode($res));
            return $res;
        } catch (\Exception $exception) {
            Log::info('调java接口失败');
            return false;
        }


    }

    //数据统一进入 clock_push_log
    public function saveIds($ids = array())
    {
        try {
            $push = new ClockPushLog();
            $data = [];
            foreach ($ids as $k => $v) {
                $data[$k]['user_id'] = $v;
                $data[$k]['status'] = 1;
                $data[$k]['create_time'] = time();
            }
            $info = $push->insertAll($data);
            return $info;
        } catch (\Exception $exception) {
            Log::info('数据统一进入库clock_push_log失败,失败的用户id为:' . json_encode($ids));
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