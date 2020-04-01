<?php
/**
 * User: danmingdong
 * Date: 2019-07-11
 * Time: 18:57
 */
namespace app\zhuanzhuan\controller;
use think\cache\driver\Redis;


class  Turn extends  Common{

    private $num = 10; #昨日赚钱排行榜的滚动数量
    private $turn_num = 20; #5元优惠券商品页面的文案轮播数量
    private $withdraw_num = 20; #新人见面礼弹窗，某某已提现滚动数据数量

    #########################redis属性设置##########################################
    public $redis; //设置redis对象
    #########################redisKEY###############################################
    const KEY = "ZHUANZHUAN-";

    public function _initialize()
    {

        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }

	/**
	 * 昨日赚钱排行榜
	 */
    public function rankEarnYesterDay()
    {
    	$key = $this::KEY . "RANK-EARN-YESTERDAY";
    	$times = 0;
    	#并发情况下，重试5次
    	while($times < 5) {
    		$times++;
    		$data = $this->handler->get($key);
	        if (empty($data)) {
	            #生成排行值
		    	$amount_arr = array();
		    	for ($i = 0; $i < $this->num; $i++) {
		    		$amount_arr[] = mt_rand(80000, 110000)/100;
		    	}
		    	sort($amount_arr);

				#随机获取10个昵称，然后按照降序组装排行数据
		    	$nicknames = $this->getNicknames($this->num);
				foreach ($nicknames as $user) {
					$amount = array_pop($amount_arr);
					$data[] = array('user' => $this->strReplace($user, '*', 1, 1), 'amount' => $amount);
				}

				if ($this->handler->set($key, json_encode($data), array('nx', 'ex' => strtotime(date("Y-m-d 23:59:59")) - time()))) {
					break;
				} else {
					usleep(1000);
				}
	        } else {
	        	$data = json_decode($data, true);
	        	break;
	        }
    	}

    	#返回接口结果
		$result['result'] = 1;
        $result['data'] = $data;
        $this->interlayer($result);
    }

    /**
	 * 新人见面礼弹窗，某某已提现滚动数据接口
	 * 昵称 已提现n元
	 */
    public function withdrawInfo()
    {
		#获取一定数量的昵称
        $nicknames = $this->getNicknames($this->withdraw_num);

        #组装昵称对应的滚动参数
        $data = [];
        foreach($nicknames as $user) {
			$item = array(
				'user' => $this->strReplace($user, '*', 1, 1), #昵称
				'amount' => mt_rand(5000, 10000)/100, #已提现n元
			);

			$data[] = $item;
        }

        #返回接口结果
        $result['result'] = 1;
        $result['data'] = $data;
        $this->interlayer($result);
    }

	/**
     * 5元页面轮播文案
     * 文案1：某某XX（1～20随机取值）秒前分享了XX个群（2～5随机取值）
     * 文案2: 某某XX（1～20随机取值）秒前赚了XX元（6.9～99随机取值）
     */
    public function fiveInfo()
    {
        #获取一定数量的昵称
        $nicknames = $this->getNicknames($this->turn_num);

        #组装昵称对应的滚动参数
        $data = [];
        foreach($nicknames as $user) {
        	$type = intval(mt_rand(10000, 29999)/10000); #文案类型，1分享了xx个群，2赚了xx元
			$item = array(
			    'type' => $type,
				'user' => $this->strReplace($user, '*', 1, 1), #某某xx
				'time' => mt_rand(1, 20), #1~20秒
				'amount' => $type == 1 ? mt_rand(2, 5) : mt_rand(690, 9900)/100, #分享了2~5个群，赚了6.9~99元
			);

			$data[] = $item;
        }

        #返回接口结果
        $result['result'] = 1;
        $result['data'] = $data;
        $this->interlayer($result);
    }

    /**
     * 获取一定数量的昵称
     */
    public function getNicknames($num=10)
    {
		$nickname_arr = config('nickname');
		shuffle($nickname_arr);
		return array_slice($nickname_arr, 0, $num);
    }

    public function strReplace($str, $replace, $start, $len)
    {
    	$find = mb_substr($str, $start, $len, 'utf-8');
		if ($find) {
			return substr_replace($str, $replace, strpos($str, $find), strlen($find));
		} else {
			return $str . $replace;
		}
    }
}