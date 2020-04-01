<?php
/**
 * 夺宝详情模块
 * Date: 2019/9/21
 * Time: 14:56
 */
namespace app\treasure\controller;
use app\treasure\model\WinPrizeShare;
use think\cache\driver\Redis;
use app\treasure\service\IndexService;
use think\Log;
class Treasuredetail extends Common
{
    const MODEL_NAME = "WinLuckyNum";
    const ATTEND_MODEL_NAME = 'WinAttendActivity';
    const ACTIVITY_MODEL_NAME = 'WinPrizeActivity';
    const ACCOUNT_MODEL_NAME = 'WinTicketAccount';
    const AWARD_MODEL_NAME = 'WinAwardList';
    const LIST_MODEL_NAME = 'WinTicketList';
    #########################redisKEY###############################################
    const KEY = "TREASURE-TREASUREDETAIL-";

    const DB_BLACK_INFO = 'common';

    const SERVICE_NAME = "TreasureticketService";

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
     * 幸运号码列表
     * $page : 页码
     * $user_id : 用户id
     * $activity_id : 活动id
     */
    public function lucky_lotto()
    {
        try {
            $request = $this->request->param();
            //用户验证
            if (empty($request['user_id']) || empty($request['uuid']) || empty($request['token']) || empty($this->goCheckToken($request['user_id'], $request['uuid'], $request['token']))) {
                return ['result' => '-11000', 'message' => config('message')['-11000']];
            }

            //活动id必传
            if (empty($request['activity_id'])) {
                $this->interlayer(['result' => '-11022']);
            }

            $key = $this::KEY . "LUCKY_NUM_LIST-USER_ID:" . $request['user_id'] . "-ACTIVITY_ID:" . $request['activity_id'];
            $redis_result = $this->redis->get($key);

            //缓存中有数据则从缓存中取，没有则读库
            if (empty($redis_result)) {
                //获取幸运号列表
                $lucky_num_list = model($this::MODEL_NAME)->lucky_num_list($request['user_id'], $request['activity_id']);
                $this->redis->set($key, $lucky_num_list, 180);
            } else {
                $lucky_num_list = $redis_result;
            }

            if (!empty($lucky_num_list)) {
                //分页
                $page = empty($request['page']) ? 1 : $request['page'];
                $result['result'] = 1;
                $result['data']['lucky_lotto_list'] = array_slice($lucky_num_list, ($page - 1) * 20, 20);
                $result['data']['total_page'] = ceil(count($lucky_num_list) / 20);
            } else {
                $result = array(
                    'result' => 1,
                    'data' => array(
                        'lucky_lotto_list' => [],
                        'total_page' => 0
                    )
                );
            }
            $this->interlayer($result);
        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[treasuredetailController:detail]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $this->interlayer(['result' => '-11029']);
        }
    }

    /**
     * 用户参与列表
     * @param int $type
     * @param array $request
     * @return mixed
     */
    public function participating_user_list($type = 1, $request = [])
    {
        try {
            if ($type == 1) {
                $request = $this->request->param();
                //用户验证
                if (empty($request['user_id']) || empty($request['uuid']) || empty($request['token']) || empty($this->goCheckToken($request['user_id'], $request['uuid'], $request['token']))) {
                    return ['result' => '-11000', 'message' => config('message')['-11000']];
                }
            }

            //活动id必传
            if (empty($request['activity_id'])) {
                $this->interlayer(['result' => '-11022']);
            }

            $key = $this::KEY . "USE_TICKET_LIST-ACTIVITY_ID:" . $request['activity_id'];
            $redis_result = $this->redis->get($key);

            //缓存中有数据则从缓存中取，没有则读库
            if (empty($redis_result)) {
                //获取参与用户列表
                $use_ticket_list = model($this::ATTEND_MODEL_NAME)->use_ticket_list($request['activity_id']);
                $this->redis->set($key, $use_ticket_list, 180);
            } else {
                $use_ticket_list = $redis_result;
            }

            if (!empty($use_ticket_list)) {
                //分页
                $page = empty($request['page']) ? 1 : $request['page'];
                $result['result'] = 1;
                $result['data']['participation_list'] = array_slice($use_ticket_list, ($page - 1) * 20, 20);
                $result['data']['total_page'] = ceil(count($use_ticket_list) / 20);
            } else {
                $result = array(
                    'result' => 1,
                    'data' => array(
                        'participation_list' => [],
                        'total_page' => 0
                    )
                );
            }
            if ($type == 1) {
                $this->interlayer($result);
            } else {
                return $result['data']['participation_list'];
            }
        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[treasuredetailController:user_list]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $this->interlayer(['result' => '-11029']);
        }
    }

    /**
     * 夺宝详情页数据
     */
    public function detail()
    {
        try {
            $request = $this->request->param();
            //用户验证
            if (empty($request['user_id']) || empty($request['uuid']) || empty($request['token']) || empty($this->goCheckToken($request['user_id'], $request['uuid'], $request['token']))) {
                return ['result' => '-11000', 'message' => config('message')['-11000']];
            }

            //活动id必传
            if (empty($request['activity_id'])) {
                $this->interlayer(['result' => '-11022']);
            }

            //获取活动信息
            $activity_info = model($this::ACTIVITY_MODEL_NAME)->activity_info($request['activity_id']);
            if (empty($activity_info)) {
                $this->interlayer(['result' => '-11028']);
            }

            $data['activity_info'] = $activity_info;
            //轮播图数据处理
            $data['activity_info']['img_turn'] = empty($activity_info['img_turn']) ? [] : json_decode($activity_info['img_turn'], true);

            //价格数据处理
            $data['activity_info']['price'] = sprintf("%.2f", $data['activity_info']['price'] / 100);

            //判断活动当前状态
            $data['status'] = ($activity_info['is_forbid'] == 1 && $activity_info['status'] == 1) ? 2 : $activity_info['status'];

            //获取进度条
            $params = array(
                'ticket' => $activity_info['ticket'],
                'ticket_admin' => $activity_info['ticket_admin'],
                'attend_ticket' => $activity_info['attend_ticket'],
                'is_forbid' => $activity_info['is_forbid'],
                'status' => $activity_info['status'],
            );
            $data['bar'] = IndexService::getProgressBar($params);

            //获取剩余夺宝券
            $data['activity_balance'] = IndexService::getDiffTicket($params);

            //获取参与用户列表
            $data['user_list'] = $this->Participating_user_list(2, $request);
            $data['have_user_list'] = empty($data['user_list']) ? 0 : 1;

            //获取用户账户余额
            $data['user_balance'] = model($this::LIST_MODEL_NAME)->getAccount($request['user_id']);

            //获取用户参与本期活动的券数
            $data['used_ticket'] = model($this::MODEL_NAME)->lucky_num_count($request['user_id'], $request['activity_id']);
            $data['is_attend'] = empty($data['used_ticket']) ? 0 : 1;

            //获取后台的夺宝设置
            $setting = model($this::SERVICE_NAME, 'service')->get_setting();

            //获取晒单信息
            $bask_model = new WinPrizeShare();
            $data['bask_info'] = $bask_model->get_bask_info($activity_info['goods_id'], $setting['bask_show_day']);
            if (!empty($data['bask_info'])) {
                $data['have_bask'] = 1;
                $data['bask_info']['activity_time'] = empty($data['bask_info']['activity_time']) ? '' : date('Y-m-d', $data['bask_info']['activity_time']);
                $data['bask_info']['img'] = empty($data['bask_info']['img']) ? '' : json_decode($data['bask_info']['img'], true);
            } else {
                $data['have_bask'] = 0;
            }

            //已结束状态下查询中奖人信息
            if ($data['status'] == 3) {
                //获取中奖人信息
                $data['award_info'] = model($this::AWARD_MODEL_NAME)->award_info($request['activity_id']);
                if (!empty($data['award_info'])) {
                    $data['award_info']['activity_time'] = date('Y-m-d', $data['award_info']['activity_time']);
                    $award_time = $data['award_info']['award_time'];
                    $data['award_info']['award_time'] = date('Y-m-d H:i:s', $data['award_info']['award_time']);

                } else {
                    $data['award_info'] = [];
                }

                //判断中奖人是否为自己
                if (!empty($data['award_info']['user_id']) && $request['user_id'] == $data['award_info']['user_id']) {
                    $data['is_me'] = 1;

                    //判断是否已填写地址
                    if (empty($data['award_info']['address_id'])) {
                        $data['have_address'] = 0;
                        //判断是否已过期
                        $time = strtotime(date("Y-m-d", time()));
                        $overdue = 86400 * 60;
                        $data['is_overdue'] = ($time - $award_time) > $overdue ? 1 : 0;
                    } else {
                        $data['have_address'] = 1;
                    }

                } else {
                    $data['is_me'] = 0;
                    $data['have_address'] = 0;
                }

                //获取本商品进行中活动信息
                $new_activity = model($this::ACTIVITY_MODEL_NAME)->new_activity($activity_info['goods_id']);

                //判断是否有正在进行中的
                $data['have_next'] = empty($new_activity) ? 0 : 1;
                $data['next_id'] = empty($new_activity['id']) ? 0 : $new_activity['id'];
                $data['next_time'] = empty($new_activity['begin_time']) ? '' : date('Y-m-d', $new_activity['begin_time']);
            }


            $data['useTicketProportion'] = floor($setting['useTicketProportion'] * $activity_info['ticket']);
            //判断用户本次可使用券数量
            if ($data['useTicketProportion'] <= $data['used_ticket']) {
                $data['enable_ticket'] = 0;
            } else {
                $data['enable_ticket'] = $data['activity_balance'] > ($data['useTicketProportion'] - $data['used_ticket']) ? ($data['useTicketProportion'] - $data['used_ticket']) : $data['activity_balance'];
                $data['enable_ticket'] = $data['user_balance'] > $data['enable_ticket'] ? $data['enable_ticket'] : $data['user_balance'];
            }
            $this->interlayer(['result' => 1, 'data' => $data]);

        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[treasuredetailController:detail]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return ['result' => '-11029'];
        }
    }
}