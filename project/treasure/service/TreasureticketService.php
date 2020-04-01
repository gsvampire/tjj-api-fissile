<?php
/**
 * 夺宝券模块
 * Date: 2019/9/20
 * Time: 21:42
 */

namespace app\treasure\service;

use app\treasure\controller\Common;
use think\Db;
use think\Log;
use app\zz\service\GrpcService;

class TreasureticketService extends Common
{
    const ACCOUNT_MODEL_NAME = 'WinTicketAccount';
    const SIGN_MODEL_NAME = 'WinSign';
    const TICKET_MODEL_NAME = 'WinTicketList';
    const ACTIVITY_MODEL_NAME = 'WinPrizeActivity';
    const LUCKY_MODEL_NAME = 'WinLuckyNum';
    #########################redisKEY###############################################
    const KEY = "TREASURE-TREASURETICKET-";
    const KEY_LIST = "TREASURE-TREASUREDETAIL-";

    /**
     * 夺宝券获取统一入口
     * @param int $type : 夺宝券来源，1-签到，2-美食屋，3-分享  4-充值送券 5-购买商品送券
     * @param int $num : 本次获取数量
     * @param int $user_id : 用户id
     * @param int $need_sign : 是否需要签到为前置条件，1-需要，2-不需要
     * @param array $opts : 扩展数组  order_id
     * @return array
     */
    public function get($type = 0, $num = 0, $user_id = 0, $need_sign = 1, $opts = [])
    {
        try {
            //类型不能为空
            if (!$type) {
                return ['result' => '-11002', 'message' => config('message')['-11002']];
            }

            //夺宝券数量不能为空
            if (!$num) {
                return ['result' => '-11001', 'message' => config('message')['-11001']];
            }

            //用户加锁，锁时间为两分钟
            $key = $this::KEY . "USER-LOCK-USER_ID:" . $user_id;
            $redis_service = new RedisService();

            //查询用户锁是否开启
            if (empty($redis_service->lock($key, 60))) {
                return ['result' => '-11036'];
            }

            //验证用户是否今日已签到，未签到的用户无法获得夺宝券
            if ($type != 1 && $need_sign == 1) {
                //获取用户今日签到数据
                $is_sign = model($this::SIGN_MODEL_NAME)->user_sign($user_id);
                if (empty($is_sign)) {
                    //释放用户锁
                    $redis_service->del($key);
                    return ['result' => '-11003', 'message' => config('message')['-11003']];
                }
            }

            //查询用户当日账户信息
            $account = model($this::TICKET_MODEL_NAME)->today_account($user_id);
            $account_num = isset($account['ticket']) ? $account['ticket'] : 0;

            //获取后台设置的夺宝券配置
            $setting = $this->get_setting();

            //用户每日领取上限
            $dailyTicketMax = $setting['dailyTicketMax'];
            //夺宝券有效期
            $ticketIndate = $setting['ticketIndate'];
            //单个订单得券上限
            $order_num = $setting['order_num'];

            //用户单笔订单得券数量不得超过上限
            if($type == 4 || $type == 5){
                $num = $num > $order_num ? $order_num : $num;
            }

            //验证用户是否已超过当日获取限额
            if ($account_num >= $dailyTicketMax) {
                $redis_service->del($key);
                return ['result' => '-11004', 'message' => config('message')['-11004']];
            }

            //给用户发放夺宝券
            $num = ($num + $account_num) > $dailyTicketMax ? $dailyTicketMax - $account_num : $num;
            $ticket_get = $this->ticket_get($user_id, $num, $ticketIndate, $type, $account, $opts);


            //报警监控
            $service = new AlarmService();
            $service->alarm($num);

            //释放用户锁
            $this->redis->rm($key);

            //清除用户账号缓存
            IndexService::clearTicketAccount($user_id);

            //清除券明细表缓存
            IndexService::clearTicketDetail($user_id);

            //清除夺宝经历缓存
            IndexService::clearExperience($user_id);


            if (!empty($ticket_get) && $ticket_get == 1) {
                return ['result' => '1', 'message' => config('message')['1'], 'num' => $num];
            } else {
                $result = empty($ticket_get) ? '-11007' : $ticket_get;
                return ['result' => $result, 'message' => config('message')[$result]];
            }
        } catch (\Exception $e) {
            $key = $this::KEY . "USER-LOCK-USER_ID:" . $user_id;
            $redis_service = new RedisService();
            $redis_service->del($key);
            Log::info("[夺宝活动]-[treasureticketService:get]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return ['result' => '-11029', 'message' => config('message')['-11029']];
        }
    }

    /**
     * 夺宝券获取信息入库
     * @param $user_id : 用户id
     * @param $num : 本次获取券数量
     * @param $ticketIndate : 失效天数
     * @param $type : 夺宝券来源
     * @param $account : 用户今日账户数据
     * @param $opts : 扩展参数
     * @return int|string
     */
    private function ticket_get($user_id, $num, $ticketIndate, $type, $account, $opts = [])
    {
        //开启事务
        Db::startTrans();

        //夺宝券明细表数据写入
        $ticket_insert = model($this::TICKET_MODEL_NAME)->ticket_insert($user_id, $num, $ticketIndate, $type, $opts);

        //明细表数据插入失败
        if (empty($ticket_insert)) {
            Db::rollback();
            return '-11005';
        } else {
            //清除用户账号缓存
            IndexService::clearTicketAccount($user_id);

            //夺宝券获取成功
            Db::commit();
            return 1;
        }

        //对用户今日账户数据进行维护（10.22废除）
//        if (empty($account)) {
//            //用户今日暂无账户数据，需新增账户数据
//            $account_result = model($this::ACCOUNT_MODEL_NAME)->account_insert($user_id, $num, $ticketIndate);
//        } else {
//            //用户今日已有账户数据，需更新已有数据
//            $account_result = model($this::ACCOUNT_MODEL_NAME)->account_update($user_id, $num, 2);
//        }
//        if (empty($account_result)) {
//            //账户表数据操作失败
//            Db::rollback();
//            return '-11006';
//        } else {
//            //清除用户账号缓存
//            IndexService::clearTicketAccount($user_id);
//
//            //夺宝券获取成功
//            Db::commit();
//            return 1;
//        }
    }

    /**
     * @param int $user_id
     * @param int $num
     * @param int $activity_id
     * @param int $goods_id
     * @return array
     */
    public function ticket_use($user_id = 0, $num = 0, $activity_id = 0, $goods_id = 0)
    {
        try {
            //夺宝券数量不能为空
            if (!$num) {
                return ['result' => '-11001'];
            }

            //活动id不能为空
            if ($activity_id) {
                //获取活动信息
                $activity_info = model($this::ACTIVITY_MODEL_NAME)->activity_info($activity_id);
                if (empty($activity_info)) {
                    //该活动不存在
                    return ['result' => '-11008'];
                }
            } else {
                return ['result' => '-11009'];
            }

            //获取用户今日签到数据
            $is_sign = model($this::SIGN_MODEL_NAME)->user_sign($user_id);
            if (empty($is_sign)) {
                return ['result' => '-11003'];
            }

            //本次活动需要夺宝券总数
            $activity_total_ticket = floor($activity_info['ticket'] * 0.2 + $activity_info['ticket_admin'] * 0.8);
            //夺宝券投放超量
            if (($activity_info['attend_ticket'] + $num) > $activity_total_ticket) {
                return ['result' => '-11021'];
            }

            //商品id不能为空且必须和活动对应的商品id一致
            if (empty($goods_id) || $activity_info['goods_id'] != $goods_id) {
                return ['result' => '-11010'];
            }

            //查询用户当前夺宝券余额
            $ticket_balance = model('WinTicketList')->getAccount($user_id);

            //用户余额为零
            if (empty($ticket_balance)) {
                return ['result' => '-11011'];
            }

            //用户余额不足
            if ($ticket_balance < $num) {
                return ['result' => '-11012'];
            }

            //获取用户本次活动已使用夺宝券数量
            $lucky_num_count = model($this::LUCKY_MODEL_NAME)->lucky_num_count($user_id, $activity_id);
            $used_ticket = empty($lucky_num_count) ? 0 : $lucky_num_count;

            //获取后台设置的夺宝券配置
            $setting = $this->get_setting();

            //用户参与单个活动的投放上限
            $useTicketProportion = $setting['useTicketProportion'];

            //夺宝券有效期
            $ticketIndate = $setting['ticketIndate'];

            //后台设置的前端、后台券数量
            $ticket_frontweb = empty($activity_info['ticket']) ? 0 : $activity_info['ticket'];

            //用户本次使用券数量不得高于活动需要券的千分之五（可配置）
            if (($used_ticket + $num) > floor($ticket_frontweb * $useTicketProportion)) {
                return ['result' => '-11013'];
            }

            //夺宝券投放
            $launch_result = $this->launch($user_id, $activity_id, $num, $activity_info['pre_key'], $activity_info['begin_time'], $activity_info['attend_ticket'], $activity_total_ticket);

            //清除用户账号缓存
            IndexService::clearTicketAccount($user_id);

            //清除券明细表缓存
            IndexService::clearTicketDetail($user_id);

            //清除夺宝经历
            IndexService::clearExperience($user_id);

            if (!empty($launch_result) && $launch_result == 1) {
                return ['result' => '1'];
            } else {
                $result = empty($launch_result) ? '-11002' : $launch_result;
                return ['result' => $result];
            }
        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[treasureticketService:ticket_use]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return ['result' => '-11029'];
        }
    }

    /**
     * 夺宝券投放信息入库
     * @param $user_id : 用户id
     * @param $activity_id : 活动id
     * @param $num : 券数量
     * @param string $pre_key : 幸运号商品前缀
     * @param int $begin_time : 活动开始时间
     * @param int $attend_ticket : 活动已投放券总量
     * @param int $activity_total_ticket : 活动需要券总量
     * @return string
     */
    private function launch($user_id, $activity_id, $num, $pre_key = '', $begin_time = 0, $attend_ticket = 0, $activity_total_ticket = 0)
    {
        //开启事务
        Db::startTrans();

        //10.22废除
//        //扣减用户当日账户余额
//        $account_result = model($this::ACCOUNT_MODEL_NAME)->account_update($user_id, $num, 2, 2);
//
//        //余额扣减失败
//        if (empty($account_result)) {
//            Db::rollback();
//            return '-11006';
//        }

        //查询用户所有有效夺宝券
        $ticket_unused = model($this::TICKET_MODEL_NAME)->ticket_unused($user_id);

        //用户没有有效的夺宝券
        if (empty($ticket_unused)) {
            Db::rollback();
            return '-11016';
        }

        $i = 0;
        $unused_num = $num;
        //按时间顺序计算用户夺宝券扣减数量
        foreach ($ticket_unused as $key => $val) {
            if (!empty($val['left_num']) && $unused_num > 0) {
                $ticket_update_data[$i]['left_num'] = $val['left_num'] >= $unused_num ? $unused_num : $val['left_num'];
                $ticket_update_data[$i]['id'] = $val['id'];
                //用于确认账户余额
                $unused_num = $unused_num - $ticket_update_data[$i]['left_num'];
                //用于确认幸运号码所绑定的夺宝券
                $ticket_update_data[$i]['num'] = $num - $unused_num;
                $i++;
            }
        }

        //账户余额不足
        if ($unused_num != 0) {
            Db::rollback();
            return '-11015';
        }

        //扣减夺宝券剩余数量
        foreach ($ticket_update_data as $k => $v) {
            $update_result = model($this::TICKET_MODEL_NAME)->ticket_update($v['id'], $v['left_num']);
            //夺宝券明细表更新失败
            if (empty($update_result)) {
                Db::rollback();
                return '-11017';
            }
        }

        //获取用户信息
        $grpcService = new GrpcService();
        $user_info = $grpcService->singleUserInfo($user_id);
        if (empty($user_info)) {
            $nickname = '淘集集用户';
            $avatar = '';
        } else {
            if (empty($user_info['nickName'])) {
                //用户没有昵称，用手机号隐藏四位代替
                $nickname = empty($user_info['userName']) ? '淘集集用户' : substr_replace($user_info['userName'], '****', 3, 4);
                $avatar = empty($user_info['avatar']) ? '' : $user_info['avatar'];
            } else {
                $nickname = $user_info['nickName'];
                $avatar = empty($user_info['avatar']) ? '' : $user_info['avatar'];
            }
        }

        //查看当前活动最新的幸运号
//        $last_lucky_num = model($this::LUCKY_MODEL_NAME)->last_lucky_num($activity_id);
//        $number = empty($last_lucky_num['number']) ? 1 : $last_lucky_num['number'] + 1;
        $number = $this->lucky_num($activity_id);
        //幸运号不能超过六位
        if ($number + $num > 999999) {
            Db::rollback();
            return '-11035';
        }
        for ($j = 0; $j < $num; $j++) {
            $lucky_num_data[$j]['lucky_num'] = $pre_key . date('md', $begin_time) . sprintf("%06d", $number + $j);
            $lucky_num_data[$j]['number'] = $number + $j;
            $lucky_num_data[$j]['activity_id'] = $activity_id;
            $lucky_num_data[$j]['user_id'] = $user_id;
            $lucky_num_data[$j]['nickname'] = $nickname;
            $lucky_num_data[$j]['avatar'] = $avatar;
            foreach ($ticket_update_data as $k => $v) {
                if ($j < $v['num']) {
                    $lucky_num_data[$j]['ticket_id'] = $v['id'];
                    break;
                }
            }
        }
        $lucky_num_insert_result = model($this::LUCKY_MODEL_NAME)->lucky_insert($lucky_num_data);

        //幸运号码插入失败
        if (empty($lucky_num_insert_result) || $lucky_num_insert_result != $num) {
            Db::rollback();
            return '-11018';
        }

        //活动剩余所需券数量
        $left_over_ticket = $activity_total_ticket - $attend_ticket - $num;

        //活动表参与总券数字段更新
        if (($attend_ticket + $num) == $activity_total_ticket) {
            $activity_update_result = model($this::ACTIVITY_MODEL_NAME)->activity_update($activity_id, $num, 0);
        } else {
            $activity_update_result = model($this::ACTIVITY_MODEL_NAME)->attend_ticket_inc($activity_id, $num, $left_over_ticket);
        }

        //活动表更新失败
        if (empty($activity_update_result)) {
            Db::rollback();
            return '-11019';
        }

        //活动信息反查
        $activity_info = model($this::ACTIVITY_MODEL_NAME)->activity_info($activity_id);

        //用户投放夺宝券导致夺宝券投入超量
        if ($activity_info['attend_ticket'] > $activity_total_ticket) {
            Db::rollback();
            return '-11020';
        }

        //参与记录数据写入
        $temp['user_id'] = $user_id;
        $temp['activity_id'] = $activity_id;
        $temp['goods_id'] = $activity_info['goods_id'];
        $temp['ticket_num'] = $num;
        $temp['nick_name'] = $nickname;
        $temp['avatar'] = $avatar;
        $attend = IndexService::attendActivity($temp);
        if (empty($attend)) {
            Db::rollback();
            return '-11014';
        }

        //幸运号码数量
        $this->redis->set("LUCEKY_NUMBER-ACTIVITY_id:" . $activity_id, $number + $num - 1, $this::EXPIRATION_ONEWEEK);

        //本期活动已投放券数量记录
        $key_attend = $this::KEY . "ATTEND-ACTIVITY_ID:" . $activity_id;
        $this->redis->set($key_attend, $activity_info['attend_ticket'], $this::EXPIRATION_ONEWEEK);

        //删除幸运号码列表缓存
        $key_list = $this::KEY_LIST . "LUCKY_NUM_LIST-USER_ID:" . $user_id . "-ACTIVITY_ID:" . $activity_id;
        $this->redis->rm($key_list);

        //删除参与用户列表缓存
        $key_user_list = $this::KEY_LIST . "USE_TICKET_LIST-ACTIVITY_ID:" . $activity_id;
        $this->redis->rm($key_user_list);


        Db::commit();
        return '1';
    }

    /**
     * 获取后台夺宝券配置
     */
    public function get_setting()
    {
        //获取后台设置的夺宝券配置
        $key = "TREASURETICKET-SETTING";
        $setting = $this->redis->get($key);
        $setting = empty($setting) ? [] : json_decode($setting, true);

        $result = array(
            'useTicketProportion' => empty($setting['percentage']) ? config('useTicketProportion') : $setting['percentage'] / 1000000,//用户参与单个活动的投放上限
            'ticketIndate' => empty($setting['validity']) ? config('ticketIndate') : $setting['validity'],//夺宝券有效期
            'dailyTicketMax' => empty($setting['day_num']) ? config('dailyTicketMax') : $setting['day_num'],//单用户每天最多可获得券数量
            'order_num' => empty($setting['order_num']) ? config('order_num') : $setting['order_num'],//单个订单得券上限
            'total_num_1' => empty($setting['total_num_1']) ? config('total_num_1') : $setting['total_num_1'],//每日报警第一级阈值
            'total_num_2' => empty($setting['total_num_2']) ? config('total_num_2') : $setting['total_num_2'],//每日报警第二级阈值
            'total_num_3' => empty($setting['total_num_3']) ? config('total_num_3') : $setting['total_num_3'],//每日报警第三级阈值
            'bask_show_day' => config('bask_show_day'),//每日报警第三级阈值
            'front_page'    => !empty($setting['floorpage_url']) ? $setting['floorpage_url'] : ''
        );
        return $result;
    }


    /**
     * 获取当前幸运号数字
     * @param $activity_id
     * @return int|mixed
     */
    private function lucky_num($activity_id)
    {
        $key = "LUCEKY_NUMBER-ACTIVITY_id:" . $activity_id;
        $redis_result = $this->redis->get($key);
        if (empty($redis_result)) {
            //查看当前活动最新的幸运号
            $last_lucky_num = model($this::LUCKY_MODEL_NAME)->last_lucky_num($activity_id);
            $number = empty($last_lucky_num['number']) ? 1 : $last_lucky_num['number'] + 1;
        } else {
            $number = $redis_result + 1;
        }
        return $number;
    }


    /**
     * @param $order_id
     * @return bool
     */
    public function take_back_ticket($order_id)
    {
        return model(self::TICKET_MODEL_NAME)->take_back_ticket($order_id);
    }
}