<?php
/**
 * 报警模块
 * Date: 2019/9/24
 * Time: 17:08
 */
namespace app\treasure\service;
use app\treasure\controller\Common;
use app\treasure\model\WinTicketList;
use think\cache\driver\Redis;
use ding\robotFactory;
class AlarmService extends Common
{
    public function _initialize()
    {
        $request = $this->request->param();
        $this->filter($request);
        try {
            $this->redis = new Redis(config('redis'));
            $this->handler = $this->redis->handler();
        } catch (Exception $e) {
            $this->apiLog($_REQUEST, $e->getMessage(), $_SERVER);
        }
    }

    /**
     * 报警监控
     * @return bool
     */
    public function alarm($num)
    {
        //占用报警操作
        $key_lock = "TREASURE-ALARM-DATE:".date("Y-m-d");
        $redis_service = new RedisService();
        if (empty($redis_service->lock($key_lock, 60))) {
            return true;
        }

        $daily_total = $this->total_ticket_get($num);//今日发券总数量

        //获取夺宝券设置
        $service = new TreasureticketService();
        $setting = $service->get_setting();

        //今日没有发券或发券数量不足阈值
        if (empty($daily_total) || $daily_total < $setting['total_num_1']) {
            $redis_service->del($key_lock);
            return true;
        }

        $key_alarmed = "TREASURE-TREASURETICKET-DAILY_ALARM-NUM:" . date("Y-m-d");
        $daily_alarm_num = $this->redis->get($key_alarmed);//今日已报警次数
        $daily_alarm_num = empty($daily_alarm_num) ? 0 : $daily_alarm_num;

        for ($i = 1; $i < 4; $i++) {
            if ($daily_alarm_num < $i && $daily_total >= $setting['total_num_' . $i]) {
                //触发报警
                $res = robotFactory::createRobot("Treasure", $setting['total_num_' . $i]);
                $data = $res->notice();
                break;
            }
        }
        if (!empty($data) && $data['res'] == 0) {
            $this->redis->set($key_alarmed, $daily_alarm_num + 1, $this::EXPIRATION_ONEDAY);
        }
        $redis_service->del($key_lock);
        return true;
    }

    /**
     * 今日投放总量
     * @param $num : 本次投放数量
     * @return float|int|mixed
     */
    public function total_ticket_get($num){
        $key = "TREASURE-TREASURETICKET-DAILY_TOTAL_TICKET:" . date("Y-m-d");
        $daily_total = $this->redis->get($key);//今日发券总数量

        //缓存中无数据，查库
        if (empty($daily_total)) {
            $model = new WinTicketList();
            $daily_total = $model->daily_total_ticket();
        }else{
            $daily_total = $daily_total + $num;
        }
        $this->redis->set($key, $daily_total, 600);
        return $daily_total;
    }
}