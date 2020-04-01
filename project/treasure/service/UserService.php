<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-09-20
 * Time: 16:12
 */

namespace app\treasure\service;

use app\treasure\model\WinAwardList;
use app\zz\service\GrpcService;
use think\Controller;
use app\treasure\model\WinSign;
use app\treasure\model\WinPrizeActivity;
use app\treasure\model\WinLuckyNum;
use app\treasure\model\WinFaward;
use think\Db;
use think\Log;
use think\Request;
use think\cache\driver\Redis;
use app\treasure\controller\Common as CommonController;
use app\treasure\service\IndexService;

class UserService extends Controller
{
   //todo 更改为上线时间 找产品确认
    protected $onlineTime='2019-10-29';//上线时间  开奖风控要用

    protected $redis;

    protected $qiantaiPeizhi = 'TREASURE-TREASURETICKET-SETTING';//前台配置夺宝券数量的的百分比

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->redis = new Redis(config('redis'));
    }

    //假头像地址
    protected $avatarUrl = "https://img-g.taojiji.com/user-avatar/20190920/20190920/";

    //获取用户最新的签到时间
    public function getUserLatelyTime($userId)
    {
        try {
            $mSign = new WinSign();
            $lastSignTime = $mSign->where('user_id', $userId)->value('last_sign_time');
            return $lastSignTime;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //添加用户签到信息
    public function addSignInfo($userId)
    {
        try {
            $arr = [
                'user_id' => $userId,
                'serial_days' => 1,
                'last_sign_time' => date('Y-m-d'),
            ];
            $mSign = new WinSign();
            $info = $mSign->insert($arr);
            return $info;
        } catch (\Exception $exception) {
            return false;
        }

    }

    //更新连续签到字段
    public function saveSerialDays($userId, $num)
    {

        try {
            if (empty($num))
                return true;
            $mSign = new WinSign();
            if ($num == 1) {
                $info = WinSign::where('user_id', $userId)
                    ->update(['serial_days' => WinSign::raw('serial_days+1'),
                        'last_sign_time' => date('Y-m-d')]);
                return $info;
            } else {
                $info = $mSign->save(['serial_days' => 1, 'last_sign_time' => date('Y-m-d')], ['user_id' => $userId]);
                return $info;
            }
        } catch (\Exception $exception) {
            return false;
        }
    }


    //用户连续签到的天数
    public function userSignDayInfo($userId)
    {
        try {
            $mSign = new WinSign();
            $dayInfo = $mSign->where('user_id', $userId)
                ->field('serial_days,last_sign_time')->find();
            return !empty($dayInfo) ? $dayInfo->toArray() : [];
        } catch (\Exception $exception) {
            return false;
        }

    }


    /**
     * 获用户头像信息
     * @return array
     */
    public function getIcon($num = 20)
    {

        $res = [];
        $userIconArr = array_rand(range(1, 654), $num);
        if ($num == 1) {
            $userIconArr = [$userIconArr];
        }
        $nickArr = $this->getNickName($num);
        for ($i = 0; $i < $num; $i++) {
            $res[] = [
                'nickname' => $nickArr[$i],
                'avatar' => 'https://' . config('DOMAIN_IMG_ADATAR') . '/group/userIcon/1' . $userIconArr[$i] . '.jpg',
            ];
        }
        return $res;
    }

    //获取昵称
    public function getNickName($num = 20)
    {

        $nicklist = config('nickname');
        shuffle($nicklist);
        return array_slice($nicklist, 0, $num);
    }

    //用户信息
    public function getUserInfo($userId)
    {
        try {
            $grpcService = new GrpcService();
            $userInfo = $grpcService::singleUserInfo($userId);
            if (empty($userInfo)) return false;
            return $userInfo;
        } catch (\Exception $exception) {
            return false;
        }

//        try {
//            $domain = config('API_URL_JAVA_MIDDLE') . '/user/getInfoInBulk?user_ids=';
//            $param = $userId . '&fields=nickname,avatar,username';
//            $minfo = httpGet($domain . $param);
//            $ainfo = json_decode($minfo, true);
//            return $ainfo;
//        } catch (\Exception $exception) {
//            return false;
//        }

    }


    //查询作废活动信息
    public function dodeleteInfo()
    {
        try {
            //昨天凌晨的时间戳
            $yestarday = strtotime(date('Y-m-d 00:00:00', strtotime("-25 day")));

            //查询是否有开奖的数据
            $mPriceActivity = new WinPrizeActivity();

            $cancleAward = $mPriceActivity
                ->where('begin_time', '<=', time())
                ->where('begin_time', '>=', $yestarday)
                ->where('is_forbid', 1)
                ->where('status', 1)
                ->field('id,goods_id,is_true,begin_time,ticket')->select();
            $cancleInfo = empty($cancleAward) ? [] : $cancleAward->toArray();

            if (empty($cancleInfo)) return false;
            return $cancleInfo;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //查询活动状态为待揭晓的数据
    public function waitInfo()
    {
        try {
            //昨天凌晨的时间戳
            $yestarday = strtotime(date('Y-m-d 00:00:00', strtotime("-25 day")));

            //查询是否有开奖的数据
            $mPriceActivity = new WinPrizeActivity();
            $openAward = $mPriceActivity
                ->where('begin_time', '<=', time())
                ->where('begin_time', '>=', $yestarday)
                ->where('status', 2)
                ->field('id,goods_id,is_true,begin_time,ticket')->select();

            $openInfo = empty($openAward) ? [] : $openAward->toArray();
            if (empty($openInfo)) return false;
            return $openInfo;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //准备要开奖的活动数据
    public function getPriceActivityInfo()
    {
        try {
            //昨天凌晨的时间戳
            $yestarday = strtotime(date('Y-m-d 00:00:00', strtotime("-1 day")));

            //查询是否有开奖的数据
            $mPriceActivity = new WinPrizeActivity();
            $openAward = $mPriceActivity
                ->where('begin_time', '<=', time())
                ->where('begin_time', '>=', $yestarday)
                ->where('status', 2)
                ->field('id,goods_id,is_true,begin_time')->select();
            $openInfo = empty($openAward) ? [] : $openAward->toArray();

            $cancleAward = $mPriceActivity
                ->where('begin_time', '<=', time())
                ->where('begin_time', '>=', $yestarday)
                ->where('is_forbid', 1)
                ->where('status', 1)
                ->field('id,goods_id,is_true,begin_time')->select();
            $cancleInfo = empty($cancleAward) ? [] : $cancleAward->toArray();

            $mergeInfo = array_merge($openInfo, $cancleInfo);
            if (empty($mergeInfo)) return false;
            return $mergeInfo;
        } catch (\Exception $exception) {
            return false;
        }
    }

    //假数据开奖
    public function dofalseOpenAward($data)
    {
        try {
            $data = array_values($data);
            $len = count($data);
            $randAvatar = mt_rand(1, 350);

            $mFaward = new WinFaward();
            $falseres = $mFaward->limit($randAvatar, $len)
                ->field('avatar,nick_name')->select();
            $falseres=empty($falseres)?[]:$falseres->toArray();
            if(empty($falseres)) return false;

            $luckInfo = new WinLuckyNum();
            $arr = [];
            foreach ($falseres as $k => $v) {
                $arr[$k]['avatar'] = $this->avatarUrl . $v['avatar'];
                $arr[$k]['nick_name'] = $v['nick_name'];
                $arr[$k]['award_num'] = 'zz' . date('md') . mt_rand(100000, 999999);
                $arr[$k]['award_time'] = time();
                $setpercent = $this->redis->get($this->qiantaiPeizhi);
                $setpercent = empty($setpercent) ? config('useTicketProportion') : $setpercent;
                $endFight = intval($setpercent * $data[$k]['ticket']);
                if($endFight<=1){
                    $endFight=1;
                }
                $arr[$k]['fight_coupon_num'] = mt_rand(1, $endFight);
                $arr[$k]['goods_id'] = $data[$k]['goods_id'];
                $arr[$k]['activity_id'] = $data[$k]['id'];
                $attendUser = $luckInfo->where('activity_id', $data[$k]['id'])->count();
                $arr[$k]['attend_user'] = $attendUser;
                $arr[$k]['activity_time'] = $data[$k]['begin_time'];
            }

            $aIds = array_column($arr, 'activity_id');
            $res = $this->saveWaradInfo($aIds, $arr);

            return $res;
        } catch (\Exception $exception) {
            return false;
        }

    }


    //插入到awardList表 变更priceActivity表status
    public function saveWaradInfo($aIds, $data)
    {

        if (empty($aIds) || empty($data)) return false;
        Db::startTrans();
        try {
            $insert = Db::name('win_award_list')->insertAll($data);
            if (empty($insert)) {
                Db::rollback();
                return false;
            }
            $save = Db::name('win_prize_activity')->where('id', 'in', $aIds)
                ->update(['status' => 3,'end_time'=>time()]);
            if (empty($save)) {
                Db::rollback();
                return false;
            }
            Db::commit();
            return $aIds;
        } catch (\Exception $exception) {
            Db::rollback();
            return false;
        }

    }


    //真开奖信息处理
    public function trueOpenInfo($data)
    {

        try {
            if (empty($data)) return false;

            $mLuckNum = new WinLuckyNum();
//            $commonContro = new CommonController();
            $arr = [];
            foreach ($data as $k => $v) {
                $activityId = $v['activity_id'];
                $luckNumArr = $mLuckNum->where('activity_id', $activityId)
                    ->where(['is_black'=> 0,'is_invalid' => 0])
                    ->column('lucky_num');

                if (empty($luckNumArr)) {
                    //集满情况下 ,券全是失效的  置为假开奖
                    Db::name('win_prize_activity')->where('id',$activityId)
                        ->update(['is_true'=>2,'status'=>2]);
                    continue;
                }
                //当前时间点重试五次 都失败,本次不开奖,等下个时间点开奖
                $luckyUserInfo=$this->doriskInfo($activityId,$luckNumArr);
                if(empty($luckyUserInfo)){
                    $luckyUserInfo=$this->doriskInfo($activityId,$luckNumArr);
                    if(empty($luckyUserInfo)){
                        $luckyUserInfo=$this->doriskInfo($activityId,$luckNumArr);
                        if(empty($luckyUserInfo)) {
                            $luckyUserInfo=$this->doriskInfo($activityId,$luckNumArr);
                            if(empty($luckyUserInfo)){
                                $luckyUserInfo=$this->doriskInfo($activityId,$luckNumArr);
                                if(empty($luckyUserInfo)){
                                    Db::name('win_prize_activity')->where('id',$activityId)
                                        ->update(['is_true'=>2,'status'=>2]);
                                    continue;
                                }
                            }
                        }
                    }
                }
                //从幸运号码中随机出来一个号码
//                $luckCount = count($luckNumArr);
//                $luckNumKey = mt_rand(1, $luckCount);
//                $luckNumVal = $luckNumArr[$luckNumKey - 1];
//                //判断幸运号码的所属人是否在黑名单
//                $luckyUserInfo = $mLuckNum->where('activity_id', $activityId)
//                    ->where('lucky_num', $luckNumVal)
//                    ->field('activity_id,user_id,lucky_num,nickname,avatar,number as lastnumber')
//                    ->find()->toArray();
//                $blackinfo = $commonContro->userBlackInfo($luckyUserInfo['user_id']);
//                //如果再天域值>=3 或者在黑名单中，把当前幸运号所属人更新为黑名单，不参与抽奖
//                if (!empty($blackinfo) && $blackinfo['tianyu'] >= 3 || !empty($blackinfo) && in_array('common', $blackinfo['hintInfo'])) {
//                    $mLuckNum->save(['is_black' => 1], ['activity_id' => $luckyUserInfo['activity_id'], 'lucky_num' => $luckyUserInfo['lucky_num']]);
//                    continue;
//                }

                $arr[$k]['activity_id'] = $luckyUserInfo['activity_id'];
                $arr[$k]['goods_id'] = $v['goods_id'];
                $arr[$k]['nick_name'] = $luckyUserInfo['nickname'];
                $arr[$k]['avatar'] = $luckyUserInfo['avatar'];
                $arr[$k]['award_num'] = $luckyUserInfo['lucky_num'];
                $arr[$k]['user_id'] = $luckyUserInfo['user_id'];
                $arr[$k]['activity_time'] = $v['begin_time'];
                $arr[$k]['award_time'] = time();
                $attendUser = $mLuckNum->where('activity_id', $luckyUserInfo['activity_id'])->count();
                $arr[$k]['attend_user'] = $attendUser;
                $arr[$k]['fight_coupon_num'] = $mLuckNum->where('activity_id', $luckyUserInfo['activity_id'])
                    ->where('user_id', $luckyUserInfo['user_id'])->count();

            }
            $aids = array_column($arr, 'activity_id');
            $res = $this->saveWaradInfo($aids, $arr);
           //删除中奖信息缓存-王老师
            if (!empty($res)) {
                foreach ($arr as $kid => $vid) {
                    IndexService::delAwardCache($vid['user_id']);
                }
            }
            return $res;
        } catch (\Exception $exception) {
            return false;
        }
    }



   //天域  黑名单 连续7天相关等判断信息
    public function doriskInfo($activityId,$luckNumArr)
    {
        $mLuckNum = new WinLuckyNum();
        $commonContro = new CommonController();
        $luckCount = count($luckNumArr);
        $luckNumKey = mt_rand(1, $luckCount);
        $luckNumVal = $luckNumArr[$luckNumKey - 1];
        //判断幸运号码的所属人是否在黑名单
        $luckyUserInfo = $mLuckNum->where('activity_id', $activityId)
            ->where('lucky_num', $luckNumVal)
            ->field('activity_id,user_id,lucky_num,nickname,avatar,number as lastnumber')
            ->find();
        $luckyUserInfo=empty($luckyUserInfo)?[]:$luckyUserInfo->toArray();
        if(empty($luckyUserInfo)){
            Log::info('夺宝券开奖的活动id为:'.$activityId.',在时间:'.date('Y-m-d H:i:s').',没有对应的幸运号码信息');
            return false;
        }
        $blackinfo = $commonContro->userBlackInfo($luckyUserInfo['user_id']);
        //如果再天域值>=3 或者在黑名单中，把当前幸运号所属人更新为黑名单，不参与抽奖
        if (!empty($blackinfo) && $blackinfo['tianyu'] >= 3 || !empty($blackinfo) && in_array('common', $blackinfo['hintInfo'])) {
            $mLuckNum->save(['is_black' => 1], ['activity_id' => $luckyUserInfo['activity_id'], 'lucky_num' => $luckyUserInfo['lucky_num']]);
            Log::info('夺宝券开奖的活动id为:'.$activityId.',用户id:'.$luckyUserInfo['user_id'].
                ',在时间:'.date('Y-m-d H:i:s').',的用户在黑名单中,tianyu:'.$blackinfo['tianyu'].',hints:'.json_encode($blackinfo['hintInfo']));
            return false;
        }
        //联系7天不能中奖
        $awardList=new WinAwardList();
        $userOldAwardTime=$awardList->where('user_id',$luckyUserInfo['user_id'])
            ->order('award_time desc')->limit(1)->value('award_time');
        if(!empty($userOldAwardTime)){
            $useroldYmd=date('Y-m-d',$userOldAwardTime);
        }else{
            $useroldYmd='2019-01-01';
        }
        $diff=$this->diffDays(date('Y-m-d'),$useroldYmd);
        if($diff<=7){
            Log::info('夺宝券开奖的活动id为:'.$activityId.',在时间:'.date('Y-m-d H:i:s').',用户id:'.
                $luckyUserInfo['user_id'].',上次开奖距离今天的天数为:'.$diff);
            return false;
        }
        $onlineDiffDay=$this->diffDays(date('Y-m-d'),$this->onlineTime);
        //上线2天内 出现签到订单退款 该用户不能进入中奖池
        if($onlineDiffDay<=2){
         $onlineRes=$this->tworefundInfo($luckyUserInfo['user_id']);
         if(empty($onlineRes)){
             Log::info('用户:'.$luckyUserInfo['user_id'].'在开奖后的1-2内有退款操作');
             return false;
         }
        }else{
            //上线时间大于3天，最近3天出现过退款,该用户不能进入中奖池
         $onlineRes=$this->threefundInfo($luckyUserInfo['user_id']);
         if(empty($onlineRes)){
             Log::info('用户:'.$luckyUserInfo['user_id'].'在开奖大于3天的三天内有退款操作');
             return false;
         }
        }

        return $luckyUserInfo;
    }

    public function tworefundInfo($userId)
    {
        $startTime=$this->onlineTime.' 00:00:00';
        $endTime=date('Y-m-d 23:59:59',strtotime('2 day',strtotime($this->onlineTime)));

        $info=Db::name('win_ticket_list')
             ->where('user_id',$userId)
             ->where('is_refund',1)
             ->where('create_time','>=',$startTime)
             ->where('create_time','<=',$endTime)
             ->value('id');
        if(!empty($info)) return false;
        return true;
    }

    public function threefundInfo($userId)
    {

        $startTime=date('Y-m-d 00:00:00',strtotime('-3 day'));
        $endTime=date('Y-m-d H:i:s');
        $info=Db::name('win_ticket_list')
            ->where('user_id',$userId)
            ->where('is_refund',1)
            ->where('create_time','>=',$startTime)
            ->where('create_time','<=',$endTime)
            ->value('id');
        if(!empty($info)) return false;
        return true;
    }

    //计算两个日期相差的天数
    protected function diffDays($endTime, $startTime)
    {
        $startTime = strtotime($startTime);
        $endTime = strtotime($endTime);
        $days = round(($endTime - $startTime) / 3600 / 24);
        return intval($days);
    }


    public function saveoldSign($userId,$seridays,$lasttime)
    {
        try {
            $mSign = new WinSign();
            $dayInfo = $mSign->save(['serial_days'=>$seridays,'last_sign_time'=>$lasttime],['user_id'=>$userId]);
            return $dayInfo;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function deloldsign($userId)
    {
        try {
            $res=Db::name('win_sign')->where('user_id',$userId)->delete();
            return $res;
        } catch (\Exception $exception) {
            return false;
        }
    }
}