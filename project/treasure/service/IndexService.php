<?php
namespace app\treasure\service;

use app\treasure\model\WinAddress;
use app\treasure\model\WinAttendActivity;
use app\treasure\model\WinAwardList;
use app\treasure\model\WinLuckyNum;
use app\treasure\model\WinPrizeActivity;
use app\treasure\model\WinPrizeGoods;
use app\treasure\model\WinTicketAccount;
use app\treasure\model\WinTicketList;
use think\Controller;
use think\Db;
use think\Exception;
use think\Log;


class IndexService extends Controller{


    //获取夺宝列表
    static function getList($page, $type )
    {
        switch ($type){
            case 1:
                $list = self::defaultOrder($page);
                break;
            case 2:
                $list = self::ticketOrder($page);
                break;
            case 3:
                $list = self::priceOrder($page);
                break;
            default:
                $list = [];
                break;
        }

        return $list;

    }

    /**
     * 用户中奖弹窗
     * @param $user_id
     * @return mixed
     * @throws \think\exception\DbException
     */
    static function awardDialog($user_id)
    {
        $data['img']         = '';
        $data['activity_id'] = '';
        $data['goods_name']  = '';
        $data['is_award']    = 0;
        $result = WinAwardList::getUserAward($user_id);
        if($result && $result['is_read'] == 0)
        {
            $activity_info = WinPrizeGoods::get(['id' => $result['goods_id']]);
            $data['img']         = $activity_info->img;
            $data['activity_id'] = $result['activity_id'];
            $data['goods_name']  = $activity_info->goods_name;
            $data['is_award']    = 1;
            $is_update = WinAwardList::updateUserAward($result['activity_id']);
            if($is_update)
            {
                self::delAwardCache($user_id);
            }
        }

        return $data;
    }

    /**
     * 分享后弹窗
     * @param $user_id
     * @return mixed
     */
    static function shareDialog($user_id)
    {
        $data['is_share']    = 0;
        $result = WinTicketList::getTicketInfo($user_id);
        if($result && $result['is_read'] == 0)
        {
            $data['is_share'] = 1;
            $is_update = WinTicketList::updateTicketInfo($result['id']);
            if($is_update)
            {
                self::delShareTicketCache($user_id);
            }
        }
        return $data;
    }

    /**
     * 订单弹窗
     * @param $user_id
     * @return mixed
     *
     */
    static function orderDialog($user_id)
    {

        $result = WinTicketList::getTicketOrderNum($user_id);

        $data['num'] = !empty($result) ? $result : 0;

        return $data;
    }




    /**
     * 按后台录入排序
     * @param $page
     * @return array
     */
    static function defaultOrder($page)
    {
        $page_size = config('page_size');
        $offset    = ($page - 1) * $page_size;

        $activity_list = WinPrizeActivity::getAll();
        if(empty($activity_list))
        {
            return [];
        }

        $total_num = count($activity_list);
        //数组分页
        $list = array_slice($activity_list,$offset,$page_size);

        $result = self::formatData($list);

        $total_page = ceil($total_num/$page_size);

        $data['total_page']= $total_page;
        $data['total_num'] = $total_num;
        $data['list']      = !empty($result) ? array_values($result) : [];
        $data['next_page'] = $page + 1 > $total_page ? 0 : $page + 1;

        return $data;

    }

    /**
     * 距离开奖最近排序
     * @param $page
     * @return array
     */
    static function ticketOrder($page)
    {
        $page_size = config('page_size');
        $offset    = ($page - 1) * $page_size;
        $activity_list = WinPrizeActivity::getAllByLeftTicket();

        if(empty($activity_list))
        {
            return [];
        }
        $over_active = [];
        foreach($activity_list as $key => $value){
            //已结束或作废的需放到最后
            if($value['status'] == WinPrizeActivity::STATUS_OVER ||
                $value['is_forbid'] == WinPrizeActivity::FORBID_YES){
                array_push($over_active,$value);
                unset($activity_list[$key]);
            }
        }

        $new_list = array_merge($activity_list,$over_active);

        $total_num = count($new_list);
        //数组分页
        $list = array_slice($new_list,$offset,$page_size);

        $result = self::formatData($list);

        $total_page = ceil($total_num/$page_size);
        $data['total_page']= $total_page;
        $data['total_num'] = $total_num;
        $data['list']      = !empty($result) ? array_values($result) : [];
        $data['next_page'] = $page + 1 > $total_page ? 0 : $page + 1;

        return $data;

    }

    /**
     * 按价格排序
     * @param $page
     * @return array
     */
    static function priceOrder($page)
    {
        $page_size = config('page_size');
        $offset    = ($page - 1) * $page_size;

        $activity_list = WinPrizeActivity::getAllByPrice();
        if(empty($activity_list))
        {
            return [];
        }
        $over_active = [];
        foreach($activity_list as $key => $value){
            //已结束或作废的需放到最后
            if($value['status'] == WinPrizeActivity::STATUS_OVER || $value['is_forbid'] == WinPrizeActivity::FORBID_YES){
                array_push($over_active,$value);
                unset($activity_list[$key]);
            }
        }

        $new_list = array_merge($activity_list,$over_active);
        $total_num = count($new_list);
        //数组分页
        $list = array_slice($new_list,$offset,$page_size);

        $result = self::formatData($list);

        $total_page = ceil($total_num/$page_size);
        $data['total_page']= $total_page;
        $data['total_num'] = $total_num;
        $data['list']      = !empty($result) ? array_values($result) : [];
        $data['next_page'] = $page + 1 > $total_page ? 0 : $page + 1;

        return $data;
    }

    /**
     * 获取用户的夺宝券
     * @param $user_id
     * @return mixed
     */
    static function getMyTicket($user_id)
    {
        $key = "db:my:ticket:account:{$user_id}";
        $redis = new RedisService();
        $data = $redis->get($key);
        if(!$data){
            $account = WinTicketList::getAccount($user_id);
            $data['account'] = $account;
            $redis->set($key,$data);
        }

        return $data['account'] ?? 0;

    }

    /**
     * 清除券账户缓存
     * @param $user_id
     * @return bool
     */
    static function clearTicketAccount($user_id)
    {
        try{
            Log::info("[夺宝活动]-[indexService:clearTicketAccount]-清除券账户缓存{$user_id}:time:".date("Y-m-d H:i:s",time()));
            $key = "db:my:ticket:account:{$user_id}";
            $redis = new RedisService();
            return $redis->del($key);
        }catch ( \Exception $e){
            Log::info("[夺宝活动]-[indexService:clearTicketAccount]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return false;
        }

    }

    /**
     * 清除中奖弹窗
     * @param $user_id
     * @return bool
     */
    static function delAwardCache($user_id)
    {
        try {
            Log::info("[夺宝活动]-[indexService:delAwardCache]-清除中奖缓存{$user_id}:time:".date("Y-m-d H:i:s",time()));
            $key = "db:award:dialog:user_id:{$user_id}";
            $redis = new RedisService();
            $redis->del($key);
        }catch ( \Exception $e){
            Log::info("[夺宝活动]-[indexService:delAwardCache]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return false;
        }
    }
    /**
     * 清除分享后弹窗
     * @param $user_id
     * @return bool
     */
    static function delShareTicketCache($user_id)
    {
        try {
            Log::info("[夺宝活动]-[indexService:delShareTicketCache]-清除分享后弹窗{$user_id}:time:".date("Y-m-d H:i:s",time()));
            $today = date("Y-m-d",time());
            $key   = "db:share:dialog:user_id:{$user_id}:date:{$today}";
            $redis = new RedisService();
            $redis->del($key);
        }catch ( \Exception $e){
            Log::info("[夺宝活动]-[indexService:delShareTicketCache]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return false;
        }
    }

    /**
     * 清除券明细缓存
     * @param $user_id
     * @return bool
     */
    static function clearTicketDetail($user_id)
    {
        try{
            $key   ="db:ticket:list:{$user_id}";
            $redis = new RedisService();
            $redis->del($key);

        }catch (\Exception $e){
            Log::info("[夺宝活动]-[indexService:clearTicketDetail]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return false;
        }


    }

    /**清除夺宝经历缓存
     * @param $user_id
     * @return bool
     */
    static function clearExperience($user_id)
    {
        try{
            $sql_count = "SELECT count(DISTINCT activity_id) as num
                FROM lb_win_attend_activity where user_id = {$user_id} ";
            $result =  Db::query($sql_count);
            $count = $result[0]['num'] ?? 0;

            $key = "db:attend:activity:list:{$user_id}:";
            $redis = new RedisService();
            $page_size  = config('page_size');
            $total_page = ceil($count/$page_size);
            if($total_page > 0){
                for($i = 1;$i <= $total_page; $i++)
                {
                    $redis->del($key.$i);
                }
            }
        }catch (\Exception $e){
            Log::info("[夺宝活动]-[indexService:clearExperience]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return false;
        }
    }

    /**
     * 最新开奖
     * @param $page
     * @return array
     */
    static function newestAward($page)
    {
        $page_size = config('page_size');
        $result = WinAwardList::getAwardList($page);
        if(!$result)
        {
            return [];
        }
        $award_list = $result['list'];
        $total_num  = $result['count'];
        if(!$award_list)
        {
            return [];
        }
        $goods_id = [];
        array_map(function($item) use(&$goods_id){
            array_push($goods_id,$item['goods_id']);
        },$award_list);
        $goods_info = WinPrizeGoods::getAllByIds($goods_id);

        foreach($award_list as $key => $value){
            if(isset($goods_info[$value['goods_id']]))
            {
                $award_list[$key]['goods_name']  = $goods_info[$value['goods_id']]['goods_name'];
                $award_list[$key]['specs']       = $goods_info[$value['goods_id']]['specs'];
                $award_list[$key]['price']       = sprintf("%.2f",$goods_info[$value['goods_id']]['price']/100);
                $award_list[$key]['img']         = $goods_info[$value['goods_id']]['img'];
                $award_list[$key]['award_time']  = date("Y-m-d",$value['award_time']);
                $award_list[$key]['activity_time']= date("Y-m-d",$value['activity_time']);

            }else{
                //容错处理  若有的商品出现在活动里 但是商品表不存在情况
                unset($award_list[$key]);
            }
        }
        $total_page = ceil($total_num/$page_size);
        $data['total_page']= $total_page;
        $data['total_num'] = $total_num;
        $data['list']      = array_values($award_list);
        $data['next_page'] = $page + 1 > $total_page ? 0 : $page + 1;
        return $data;
    }

    /**
     * 夺宝经历
     * @param $user_id
     * @param $page
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    static function experience($user_id,$page)
    {
        $page_size = config('page_size');
        $result = WinAttendActivity::getAttendList($user_id,$page);
        if(!$result)
        {
            return [];
        }
        $attend_list = $result['list'];
        $total_num   = $result['count'];

        if(!$attend_list)
        {
            return [];
        }
        //获取商品信息
        $goods_id = [];
        array_map(function($item) use(&$goods_id){
            array_push($goods_id,$item['goods_id']);
        },$attend_list);
        $goods_info = WinPrizeGoods::getAllByIds($goods_id);

        // 获取活动信息
        $activity_id = [];
        array_map(function($item) use(&$activity_id){
            array_push($activity_id,$item['activity_id']);
        },$attend_list);
        $activity_info = WinPrizeActivity::getActivityInfoByIds($activity_id);


        foreach ($attend_list as $key => $value){
            if(!isset($goods_info[$value['goods_id']]) ||
                !isset($activity_info[$value['activity_id']]))
            {
                unset($attend_list[$key]);
                continue;
            }
            $attend_list[$key]['ticket']      = $activity_info[$value['activity_id']]['ticket'];
            $attend_list[$key]['goods_id']    = $value['goods_id'];
            $attend_list[$key]['goods_name']  = $goods_info[$value['goods_id']]['goods_name'];
            $attend_list[$key]['specs']       = $goods_info[$value['goods_id']]['specs'];
            $attend_list[$key]['price']       = sprintf("%.2f",$goods_info[$value['goods_id']]['price']/100);
            $attend_list[$key]['img']         = $goods_info[$value['goods_id']]['img'];
            $attend_list[$key]['diff_ticket'] = self::getDiffTicket($activity_info[$value['activity_id']]);
            $attend_list[$key]['progress']    = self::getProgressBar($activity_info[$value['activity_id']]);

            $award_info = self::getButtonLogic($user_id,$activity_info,$activity_id,$value['activity_id'],$goods_id,$value['goods_id']);
            $attend_list[$key]['next_activity_id'] = $award_info['next_activity_id'];
            $attend_list[$key]['button']           = $award_info['button'];
            $attend_list[$key]['button_status']    = $award_info['button_status'];
            $attend_list[$key]['project_status']   = $award_info['project_status'];
        }


        $total_page = ceil($total_num/$page_size);
        $data['total_page']= $total_page;
        $data['total_num'] = $total_num;
        $data['list']      = array_values($attend_list);
        $data['next_page'] = $page + 1 > $total_page ? 0 : $page + 1;

        return $data;
    }

    /**
     * 券明细
     * @param $user_id
     * @param $page
     * @return array
     */
    static function ticketDetail($user_id,$page)
    {
        $page_size = config('page_size');
        $result    = WinTicketList::getTicketList($user_id);
        $offset    = ($page - 1) * $page_size;
        if(!$result)
        {
            return [];
        }
        $ticket_list = $result['list'];
        $total_num   = count($ticket_list);

        if(!$ticket_list)
        {
            return [];
        }

        $invalid_list = [];
        foreach ($ticket_list as $key => $value){

            $ticket_list[$key]['status']       = self::getTicketStatus($value);

            $ticket_list[$key]['overdue_time'] = date("Y-m-d H:i:s",$value['overdue_time']);
            $ticket_list[$key]['get_way']      = WinTicketList::$type_text[$value['type']] ?? '';

            if($ticket_list[$key]['status'] != 0)
            {
                array_push($invalid_list,$ticket_list[$key]);
                unset($ticket_list[$key]);
            }
        }
        $sort_arr = array_column($invalid_list,'create_time');
        array_multisort($sort_arr,SORT_DESC,$invalid_list);
        $new_list = array_merge($ticket_list,$invalid_list);

        //数组分页
        $list = array_slice($new_list,$offset,$page_size);
        $total_page = ceil($total_num/$page_size);
        $data['total_page']= $total_page;
        $data['total_num'] = $total_num;
        $data['list']      = $list;
        $data['next_page'] = $page + 1 > $total_page ? 0 : $page + 1;

        return $data;
    }





    /**
     * 添加 参加活动记录 供 夺宝调取
     * @param $data
     * @return bool|int|string
     */

    static function attendActivity($data){
        try{
            if(!$data['user_id'] ||
                !$data['activity_id'] ||
                !$data['goods_id'])
            {
                Log::info("[夺宝活动]-[indexService:attendActivity]-msg:参加活动记录缺少参数:data:".json_encode($data));
                return false;
            }


            $res = WinAttendActivity::addAttendInfo($data);

            return $res;
        }catch ( \Exception $e){
            Log::info("[夺宝活动]-[indexService:attendActivity]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return false;
        }

    }




    /**
     * 格式化所需数据
     * @param $activity_data
     * @return array
     */
    static function formatData($activity_data)
    {
        if(!$activity_data)
        {
            return [];
        }
        //获取指定页 获取商品信息
        $goods_id = [];
        array_map(function($item) use(&$goods_id){
            array_push($goods_id,$item['goods_id']);
        },$activity_data);
        $goods_info = WinPrizeGoods::getAllByIds($goods_id);
        //var_dump($goods_info);exit;

        foreach ($activity_data as $key => $value) {
            if(isset($goods_info[$value['goods_id']]))
            {
                $activity_data[$key]['goods_name']  = $goods_info[$value['goods_id']]['goods_name'];
                $activity_data[$key]['specs']       = $goods_info[$value['goods_id']]['specs'];
                $activity_data[$key]['price']       = sprintf("%.2f",$goods_info[$value['goods_id']]['price']/100);
                $activity_data[$key]['img']         = $goods_info[$value['goods_id']]['img'];
                $activity_data[$key]['attend_ticket'] = self::getAttendTicketByActivityId($value['activity_id'])?: $activity_data[$key]['attend_ticket'];
                $activity_data[$key]['diff_ticket'] = self::getDiffTicket($activity_data[$key]);
                $activity_data[$key]['progress']    = self::getProgressBar($activity_data[$key]);


                //if(isset($activity_data[$key]['diff_num'])) unset($activity_data[$key]['diff_num']);
                //unset($activity_data[$key]['ticket_admin'],$activity_data[$key]['attend_ticket']);
               // unset($activity_data[$key]['sort'],$activity_data[$key]['status'],$activity_data[$key]['is_forbid']);
            }else{
                //容错处理  若有的商品出现在活动里 但是商品表不存在情况
                unset($activity_data[$key]);
            }
        }

        return $activity_data;
    }

    /** 获取活动的参与券总量
     * @param $activity_id
     * @return mixed
     */
    static function getAttendTicketByActivityId($activity_id)
    {
        $key = "TREASURE-TREASURETICKET-ATTEND-ACTIVITY_ID:{$activity_id}";
        $redis = new RedisService();
        return $redis->get($key);
    }

    /*
     * 获取进度条逻辑
     * ticket  前台券
     * ticket_admin 后台券
     * attend_ticket 目前参与的券数量
     * is_forbid 作废标识
     */
    static function getProgressBar($params)
    {
        $progress = 0;
        if(!$params['ticket'] || !$params['ticket_admin'])
        {
            return $progress;
        }

        if($params['is_forbid'] == WinPrizeActivity::FORBID_YES ||
            $params['status'] == WinPrizeActivity::STATUS_OVER)
        {
            return 100;
        }

        $need_true_admin_ticket = intval($params['ticket_admin'] * 0.8);// 后台的真实券数量

        if($params['attend_ticket'] <= $need_true_admin_ticket)
        {

            $progress = bcmul(bcdiv($params['attend_ticket'],$params['ticket_admin'],4),100,2);

        }else{
            // 80% 计算逻辑  +  20%计算逻辑
            //$percent_eight = bcmul(bcdiv($params['attend_ticket'],$params['ticket_admin'],4),100,2);
            $percent_eight = 80;
            $percent_two   = bcmul(bcdiv(($params['attend_ticket'] - $need_true_admin_ticket),$params['ticket'],4),100,2);
            $progress = $percent_eight + $percent_two;
        }
        return min(100,$progress);
    }
    /*
     * 还差多少券开奖
     * ticket  前台券
     * ticket_admin 后台券
     * attend_ticket 目前参与的券数量
     * is_forbid 作废标识
     */
    static function getDiffTicket($params)
    {
        $diff_num = 0;
        if(!$params['ticket'] || !$params['ticket_admin'] ||
            ($params['is_forbid'] == WinPrizeActivity::FORBID_YES) ||
            $params['status'] == WinPrizeActivity::STATUS_OVER)
        {
            return $diff_num;
        }

        $need_true_admin_ticket = intval($params['ticket_admin'] * 0.8);// 后台的真实券数量
        if($params['attend_ticket'] <= $need_true_admin_ticket)
        {

            $true_num = intval($params['attend_ticket']/10);

        }else{

            $true_num = intval($need_true_admin_ticket/10) + ($params['attend_ticket'] - $need_true_admin_ticket);

        }

        $diff_num = $params['ticket'] - $true_num;
        return max(0,$diff_num);

    }

    /**
     * 快捷用券逻辑
     * @param $user_id
     * @param $activity_id
     * @return mixed
     */
    public static function useTicket($user_id,$activity_id)
    {
        // 获取后台配置
        $service = new TreasureticketService();
        $setting = $service->get_setting();

        //获取活动信息
        $model = new WinPrizeActivity();
        $activity_info = $model->activity_info($activity_id);

        //获取用户用券信息
        $attend_model = new WinLuckyNum();
        $used_ticket  = $attend_model->lucky_num_count($user_id,$activity_id);

        //还差多少夺宝券
        $diff_num  = self::getDiffTicket($activity_info);

        //每个活动用户可以用的数量
        $user_can_used_num = 0;
        if(isset($activity_info['ticket']))
        {
            $user_can_used_num = floor($setting['useTicketProportion'] * $activity_info['ticket']);
        }


        //用户账户余额
        $user_account = self::getMyTicket($user_id);
        //判断用户本次可使用券数量
        if ($user_can_used_num <= $used_ticket) {
            $data['enable_ticket'] = 0;
        } else {
            $data['enable_ticket'] = $diff_num > ($user_can_used_num - $used_ticket) ? ($user_can_used_num - $used_ticket) : $diff_num;
            $data['enable_ticket'] = $user_account > $data['enable_ticket'] ? $data['enable_ticket'] : $user_account;
        }
        $data['user_account'] = $user_account;
        $data['price']        = $activity_info['price'];

        return $data;


    }

    public static function getUrl()
    {
        // 获取后台配置
        $service = new TreasureticketService();
        $setting = $service->get_setting();

        return $setting['front_page'];
    }

    /**
     * 获取按钮逻辑
     * @param $user_id
     * @param $activity_info
     * @param $activity_ids
     * @param $activity_id
     * @param $goods_ids
     * @param $goods_id
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    private static function getButtonLogic($user_id,$activity_info,$activity_ids,$activity_id,$goods_ids,$goods_id)
    {
        $button = '';
        $project_status = '';
        $button_status = 0;
        $next_activity_id   = 0;
        // 进行中 且 未作废 可继续参与本期
        if($activity_info[$activity_id]['status'] == WinPrizeActivity::STATUS_ING &&
            $activity_info[$activity_id]['is_forbid'] == WinPrizeActivity::FORBID_NO)
        {
            $button         = '继续参与';
            $project_status = '进行中';
            $button_status  = 1;
        }

        //待揭晓  跳转详情页
        if($activity_info[$activity_id]['status'] == WinPrizeActivity::STATUS_WAIT ||
            ($activity_info[$activity_id]['is_forbid'] == WinPrizeActivity::FORBID_YES &&
                $activity_info[$activity_id]['status'] != WinPrizeActivity::STATUS_OVER))
        {
            $button         = '查看';
            $project_status = '揭晓中';
            $button_status  = 2;
        }

        //已开奖
        if($activity_info[$activity_id]['status'] == WinPrizeActivity::STATUS_OVER)
        {
            //用户是否中奖
            $award_info = WinAwardList::getAwardInfo($user_id,$activity_ids);
            if(isset($award_info[$activity_id]))
            {
                //中奖后 地址是否填写
                $project_status = '中奖';
                $address_info = WinAddress::getAddress($user_id,$activity_id);
                if(!$address_info && (time() - $award_info[$activity_id]['award_time']) > 60 * 24 * 3600 )
                {
                    $project_status = '未领奖';
                    $button = '';
                    $button_status = 3;
                }else{
                    if($address_info)
                    {
                        $button = '查看地址';
                        $button_status = 4;
                    }else{
                        $button = '填写地址';
                        $button_status = 5;
                    }

                }

            }else{
                $project_status = '未中奖';
                $button         = '再次参与';

                //未中奖 跳转当前产品正在进行的夺宝详情页   若没有 【已结束】
                $next_activity_info = WinPrizeActivity::getActivityInfoByGoodsId($goods_ids);

                if(isset($next_activity_info[$goods_id]))
                {
                    $next_activity_id = $next_activity_info[$goods_id]['id'];
                    $button_status  = 6;
                }else{
                    $button         = '已结束';
                    $button_status  = 7;
                }
            }
        }

        return [
            'button' => $button,
            'button_status' => $button_status,
            'project_status' => $project_status,
            'next_activity_id' => $next_activity_id,
        ];


    }


    /**
     * 获取夺宝券状态
     * @param $data
     * @return int   0 未使用    1 已使用     2 已失效（已退款）  3已失效（已过期）
     */
    private static function getTicketStatus($data)
    {
        $date = strtotime(date("Y-m-d",time()));
        if($data['overdue_time'] > $date)
        {
            if($data['left_num'] > 0)
            {
                if($data['is_refund'] == 0)
                {
                    $status = 0;
                }else{
                    $status = 2;
                }

            }else{
                if($data['is_refund'] == 0)
                {
                    $status = 1;
                }else{
                    $status = 2;
                }
            }

        }else{
            if($data['left_num'] > 0)
            {
                if($data['is_refund'] == 0)
                {
                    $status = 3;
                }else{
                    $status = 2;
                }

            }else{
                if($data['is_refund'] == 0)
                {
                    $status = 1;
                }else{
                    $status = 2;
                }
            }
        }
        return $status;
    }






}