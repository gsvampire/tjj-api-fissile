<?php
/**
 * User: danmingdong
 * Date: 2019-07-16
 * Time: 18:57
 */
namespace app\zhuanzhuan\controller;
use think\cache\driver\Redis;
use think\Db;
use think\Log;
use think\Request;
use app\zhuanzhuan\model\AnniversaryInvite;
use app\zhuanzhuan\model\AnniversaryWithdraw;

class  Anniversary extends  Common{

	#新人见面礼弹窗，某某已提现滚动数据数量
    private $award_num = 20;

    const KEY = "ZHUANZHUAN-";

    public function _initialize()
    {
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }

    /**
     * 前端面板接口
     * 卡牌、累计奖金、可提现奖金
     * 每3个一组，一组中至少有一个下单，则有可提现金额
     */
    public function index(Request $request)
    {
    	#process_id方便和他方接口追踪数据
		$process_id = microtime(true) * 10000;

    	$result = array();
    	#前端参数判断
    	$inviteUserId = $request->param('user_id');
    	Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，面板数据，process_id:'.$process_id.'，user_id：'.$inviteUserId);
        if (empty($inviteUserId)) {
	        $result['result'] = -1;
	        $this->interlayer($result);
        }

		#删除缓存注册数据
    	$invite_key = $this::KEY . 'ANNIVERSARY_INDEX_' . $inviteUserId;
		$invite_json = $this->handler->get($invite_key);
		if ($invite_json) {
			$result = json_decode($invite_json, true);
		} else {
			$mAnniversaryInvite = new AnniversaryInvite();
	    	$invite_result = $mAnniversaryInvite->where('invite_user_id', $inviteUserId)
	    		->field('invite_user_id, invited_user_id, order_status')
	    		->order('register_time','asc')
	            ->select();
	        if ($invite_result) {
				$i = 0;
	    		$amount = 0; #可提现金额
	    		$total_amount = 0; #累计奖金
	    		$card_group_amount_status = 0; #标记每3个一组的是否具备可提现条件，0不可1可
	    		#循环邀请的注册用户，计算出可提现金额
	    		foreach ($invite_result as $invate_item) {
	    	 		$i++;
	    			#下单是可提现金额条件
	    			if ($invate_item['order_status'] == 1) {
						$card_group_amount_status = 1;
	    			}
	    			#每3个为一组，对应累计奖金和可提现金额
	    			if ($i % 3 == 0) {
	    				$total_amount += 8.8;
	    				if ($card_group_amount_status) {
	    					$amount += 8.8;
	    				}
	    				$card_group_amount_status = 0;
	    			}
	    		}
				$result['result'] = 1;
	    		$result['data'] = array(
	    			'card_num' => $i,
	    			'total_amount' => $total_amount,
	    			'amount' => $amount,
	    		);
	        } else {
	        	#还没有邀请他人成功注册的数据
	        	$result['result'] = 1;
				$result['data'] = array(
	    			'card_num' => 0,
	    			'total_amount' => 0,
	    			'amount' => 0,
	    		);
	        }
	        #新建index页面缓存注册数据
	        $this->handler->setex($invite_key, 604800, json_encode($result));
		}
        $this->interlayer($result);
    }

    /**
     * 邀请他人注册成功，获取注册信息接口,java api回调地址
     * param: invite_user_id, #邀请人user_id
	 * param: invited_user_id, #被邀请人
     * param: register_time #注册时间
     */
    public function registerInfo()
    {
		try {
			#process_id方便和他方接口追踪数据
			$process_id = microtime(true) * 10000;
            $mjsonInfo = file_get_contents("php://input");
            Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，注册回调数据，process_id:'.$process_id.'，时间：'.date('Y-m-d H:i:s').'数据为：'.$mjsonInfo);

            #不在活动时间内，直接返回失败
            $start_time = config('zhuanzhuan_anniversary_start_time');
            $end_time = config('zhuanzhuan_anniversary_end_time');
			if (time() < strtotime($start_time) || time() > strtotime($end_time)) {
				echo 'ok';exit;
			}

			#解出json数据，参数校验
            $ajsonInfo = json_decode($mjsonInfo, true);
            if (!isset($ajsonInfo['activity_type']) || !isset($ajsonInfo['s_user_id']) || !isset($ajsonInfo['user_id']) || !isset($ajsonInfo['create_time']) || !isset($ajsonInfo['is_reg'])) {
				Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，注册回调数据，参数异常，process_id:' . $process_id . '，时间：' . date('Y-m-d H:i:s') . '数据为：' . $mjsonInfo.' json:' . json_encode($mjsonInfo));
				echo 'ok';exit;
            }

            #活动类型不是此活动的，丢弃
            if ($ajsonInfo['activity_type'] != 12 || $ajsonInfo['is_reg'] != 1) {
            	echo 'ok';exit;
            }

			#传参数字段名字转义
            $ajsonInfo['invite_user_id'] = $ajsonInfo['s_user_id'];
            $ajsonInfo['invited_user_id'] = $ajsonInfo['user_id'];
            $ajsonInfo['register_time'] = $ajsonInfo['create_time'];

            #用户下单数据redis key
            $register_key = $this::KEY . 'ANNIVERSARY_REGISTER_' . $ajsonInfo['invite_user_id'] . '_' . $ajsonInfo['invited_user_id'];
            $register_info = $this->handler->get($register_key);
            if ($register_info && $register_info != -1) {
            	echo 'ok';exit;
            }

			#register_info=-1，表示插入失败，此次请求查表字段对应关系，防止一直重试
			$mAnniversaryInvite = new AnniversaryInvite();
			if ($register_info == -1) {
				#查看是否已经存在该记录
				$id = $mAnniversaryInvite->where('invite_user_id', $ajsonInfo['invite_user_id'])->where('invited_user_id', $ajsonInfo['invited_user_id'])
	                ->value('id');
	            if (!empty($id)){
	            	Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，注册回调数据，记录已经存在，process_id:'.$process_id.'，时间：'.date('Y-m-d H:i:s').'数据为：'.$mjsonInfo);
	                echo 'ok';exit;
	            }
			}

			#成功邀请一位用户，插入新的数据记录
            $data = array(
        		'invite_user_id' => $ajsonInfo['invite_user_id'],
            	'invited_user_id' => $ajsonInfo['invited_user_id'],
            	'register_time' => $ajsonInfo['register_time']
            );
            $result = $mAnniversaryInvite->insert($data);
            if ($result) {
				#删除index页面缓存注册数据
            	$invite_key = $this::KEY . 'ANNIVERSARY_INDEX_' . $ajsonInfo['invite_user_id'];
				$del_num = $this->handler->del($invite_key);

				#新建下单缓存数据
				$this->handler->set($register_key, 1);
				$this->handler->expireAt($register_key, strtotime($end_time) + 86400);

				#日志
            	Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，注册回调数据，插入成功，process_id:' . $process_id . '，时间：' . date('Y-m-d H:i:s') . '数据为：' . $mjsonInfo . ' del_num:' . $del_num);
				echo 'ok';exit;
			} else {
				#更新失败，增加标识，让下一次请求查表字段对应关系，防止一直重试
				$this->handler->set($register_key, -1);
				$this->handler->expireAt($register_key, strtotime($end_time) + 86400);

				Log::error(__CLASS__ . '/' . __FUNCTION__ . ' process_id:'.$process_id.'，数据insert失败：result:' . json_encode($result).' input:'.json_encode($ajsonInfo));
				echo 'error1 process_id:'.$process_id;exit;
			}
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' process_id:'.$process_id.'，系统发生异常,错误信息为：' . $exception->getMessage());
            echo 'error2 process_id:'.$process_id;exit;
        }
    }

    /**
     * 邀请他人注册成功后，并且下单了，获取下单用户信息接口
     * invited_user_id, #被邀请人
	 * order_id, #下单id
	 * order_time, #注册时间
     */
	public function orderInfo()
	{
		try {
			#process_id方便和他方接口追踪数据
			$process_id = microtime(true) * 10000;
            $mjsonInfo = file_get_contents("php://input");
            Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，下单回调数据，process_id:' . $process_id . '，时间：' . date('Y-m-d H:i:s') . '数据为：' . $mjsonInfo);

            #不在活动时间内，直接返回失败
            $start_time = config('zhuanzhuan_anniversary_start_time');
            $end_time = config('zhuanzhuan_anniversary_end_time');
			if (time() < strtotime($start_time) || time() > strtotime($end_time)) {
				echo 'ok';exit;
			}

			#解出json数据，参数校验
            $ajsonInfo = json_decode($mjsonInfo, true);
            if (!isset($ajsonInfo['activity_type']) || !isset($ajsonInfo['user_id']) || !isset($ajsonInfo['s_user_id']) || !isset($ajsonInfo['order_id']) || !isset($ajsonInfo['order_time'])) {
				Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，下单回调数据，参数异常，process_id:' . $process_id . '，时间：' . date('Y-m-d H:i:s') . '数据为：' . $mjsonInfo . ' json:' . json_encode($mjsonInfo));
				echo 'ok';exit;
            }

            #活动类型不是此活动的，丢弃
            if ($ajsonInfo['activity_type'] != 12) {
            	echo 'ok';exit;
            }

			#传参数字段名字转义，被邀请者
            $ajsonInfo['invited_user_id'] = $ajsonInfo['user_id'];
            $ajsonInfo['invite_user_id'] = $ajsonInfo['s_user_id'];

            #用户下单数据redis key
            $order_key = $this::KEY . 'ANNIVERSARY_ORDER_' . $ajsonInfo['invite_user_id'] . '_' . $ajsonInfo['invited_user_id'];

			#查看是否已经存在该记录
			$order_info = $this->handler->get($order_key);
			if ($order_info && $order_info != -1) {
				echo 'ok';exit;
			}

			#order_info=-1，表示更新失败，此次请求查表字段对应关系，防止一直重试
			$mAnniversaryInvite = new AnniversaryInvite();
			if ($order_info == -1) {
				$record = $mAnniversaryInvite->where(array('invite_user_id' => $ajsonInfo['invite_user_id']))->where('invited_user_id', $ajsonInfo['invited_user_id'])
	                ->field('order_id')
	                ->select();

	            if (!$record) {
	            	Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，下单回调数据,找不到对应的invited_user_id，process_id:' . $process_id.'，时间：' . date('Y-m-d H:i:s') . '数据为：' . $mjsonInfo);
	            	echo 'ok';exit;
	            }

	            if (!empty($record[0]['order_id'])){
	            	Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，注册回调数据，已有下单数据，process_id:' . $process_id.'，时间：' . date('Y-m-d H:i:s') . '数据为：' . $mjsonInfo);
	                echo 'ok';exit;
	            }
			}

			#被邀请人成功下单，更改状态
            $update = $mAnniversaryInvite->where(array('invite_user_id' => $ajsonInfo['invite_user_id']))->where(array('invited_user_id' => $ajsonInfo['invited_user_id']))
                ->update(array('order_id' => $ajsonInfo['order_id'], 'order_status' => 1, 'order_time' => $ajsonInfo['order_time']));
			if ($update) {
				#删除index页面缓存注册数据
            	$invite_key = $this::KEY . 'ANNIVERSARY_INDEX_' . $ajsonInfo['invite_user_id'];
				$del_num = $this->handler->del($invite_key);

				#新建下单缓存数据
				$this->handler->set($order_key, 1);
				$this->handler->expireAt($order_key, strtotime($end_time) + 86400);

				#日志
				Log::info(__CLASS__ . '/' . __FUNCTION__ . '赚赚周年庆，注册回调数据，下单成功，process_id:'.$process_id.'，时间：' . date('Y-m-d H:i:s').'数据为：' . $mjsonInfo . ' del_num:' . $del_num);
				echo 'ok';exit;
			} else {
				#更新失败，增加标识，让下一次请求查表字段对应关系，防止一直重试
				$this->handler->set($order_key, -1);
				$this->handler->expireAt($order_key, strtotime($end_time) + 86400);

				#日志
				Log::error(__CLASS__ . '/' . __FUNCTION__ . ' error1 process_id:'.$process_id.'，数据库更新失败：result:' . json_encode($update) . ' input:'.json_encode($ajsonInfo));
				echo 'error1 process_id:'.$process_id;exit;
			}
        } catch (\Exception $exception) {
            Log::error(__CLASS__ . '/' . __FUNCTION__ . ' error2 process_id:' . $process_id.'，系统发生异常,错误信息为：' . $exception->getMessage());
            echo 'error2 process_id:'.$process_id;exit;
        }
	}

	/**
	 * 更改退款订单
	 */
	public function refundOrder()
	{
		echo "ok";exit;

		try{
			$mAnniversaryInvite = new AnniversaryInvite();
			$max_times = 50;
			$start = 0;
			$success = 0;
			$size = 1000;
			while ($start < $max_times) {
				$res = $mAnniversaryInvite->field('id, invite_user_id, order_status, order_id')->limit($start * 1000, $size)->select();
				if ($res) {
					foreach($res as $item) {
						if($item['order_status'] == 1) {
							$params = array(
					            'user_id' => $item['invite_user_id'],
					            'order_id' => $item['order_id'],
					            'fields' => 'order_state',
					            'is_detail' => 1,
					            'detail_fields' => 'od_id, order_state'
					        );
					        echo '发送Api2_5_0/order/getOrderByOrderId奖励参数：' . json_encode($params) . "\r\n";
					        $info = api('Api2_5_0/order/getOrderByOrderId', $params, false, config('DOMAIN_API_TJJ_SERVICE'));
					        echo '主流程返回：' . json_encode($info) . "\r\n";
					        if ($info && isset($info['result']) && $info['result'] == 1) {
								if($info['data']) {
									if($info['data']['order_state'] != 10) {
										$update = $mAnniversaryInvite->where(array('id' => $item['id']))->update(array('order_status' => 2));
										if ($update) {
											$success++;
											echo 'invite_user_id:' . $item['invite_user_id'].' id:' . $item['id']." success\r\n";
											continue;
										} else {
											echo 'invite_user_id:' . $item['invite_user_id'].' id:' . $item['id']." update failed\r\n";
										}
									}

									if($info['data']['_order_detail']) {
										foreach($info['data']['_order_detail'] as $itemDetail) {
											if($itemDetail['order_state'] != 10) {
												$update = $mAnniversaryInvite->where(array('id' => $item['id']))->update(array('order_status' => 2));
												if ($update) {
													$success++;
													echo 'detail od_id:' . $itemDetail['od_id'] . 'invite_user_id:' . $item['invite_user_id'].' id:' . $item['id']." success\r\n";
													break;
												} else {
													echo 'detail od_id:' . $itemDetail['od_id'] . 'invite_user_id:' . $item['invite_user_id'].' id:' . $item['id']." update failed\r\n";
												}
											}
										}
									}
								}
					        } else {
					        	echo '主流程结果异常：' . json_encode($info).' 参数：' . json_encode($params)."\r\n";
					        }

					        unset($params);
					        unset($info);
						}
					}
				} else {
					break;
				}
				unset($res);
				$start++;
				usleep(1000);
			}

		} catch (\Exception $exception) {
           	var_dump($exception->getMessage());
        }
	}

	/**
	 * 去除黑名单
	 */
	public function black()
	{
		echo "ok";exit;
		try{
			$redisT = new Redis(config('blackRedis'));
	        $handler = $redisT->handler();

			#查看是否已经存在该记录
			$mAnniversaryWithdraw = new AnniversaryWithdraw();
			$max_id = $mAnniversaryWithdraw->order('id','desc')->limit(1)->value('id');
	        if (empty($max_id)){
	        	echo "black获取anniversary_withdraw最大ID失败!\r\n";
	        	exit;
	        }

			#次数异常次数，则停止
			$exception_time = 0;

			#成功次数
			$success = 0;
			#发送奖励
			for ($i = 1; $i <= $max_id; $i++) {
				try{
					$res = $mAnniversaryWithdraw->where('id', $i)->field('invite_user_id, status, amount')->select();
					if ($res && $res[0]['status'] == 0) {
						#接口发送
						$item = $res[0];
						if ($item['amount'] > 0) {
							#确认是否是黑名单//zzblacklist_114563450686098861
							echo "开始检测invite_user_id:" . $res[0]['invite_user_id'].' id:' . $i ."\r\n";
							$blackUser = array(139852862196230629,39789808817509079,78994649288597413,139857951145318641,139854494506095812,139855197712143644,44425641944409141,97229389359747722,139852601433503301,139853766856496329,139865306637404702,131558410743831456,139859855658714832,132373616273493849,41623051425966742);
							if($handler->exists("zzblacklist_" . $res[0]['invite_user_id']) || in_array($res[0]['invite_user_id'], $blackUser)) {
								$update = $mAnniversaryWithdraw->where(array('id' => $i))->update(array('status' => 2));
								if ($update) {
									$success++;
									echo 'invite_user_id:' . $res[0]['invite_user_id'].' id:' . $i ." success\r\n";
								} else {
									echo 'invite_user_id:' . $res[0]['invite_user_id'].' id:' . $i ." update failed\r\n";
								}
							}
						}
					}
					$exception_time = 0;
					unset($res);
				} catch (\Exception $exception) {
					echo 'black Exception:' . json_encode($exception->getMessage()) . "\r\n";
					$exception_time++;
					if($exception_time > 20) {
						echo 'black Exception次数太多，停止发送, 异常次数:' . $exception_time . "\r\n";
						exit;
					}
		        }
				usleep(100);
			}
			echo 'black finish success:' . $success . "\r\n";
		} catch (\Exception $exception) {
           	var_dump($exception->getMessage());
        }
	}

	/**
	 * 备份数据
	 */
	public function backup()
	{
		echo "ok";exit;
		$mAnniversaryInvite = new AnniversaryInvite();
		$max_times = 50;
		$start = 0;
		$success = 0;
		$size = 1000;
		while ($start < $max_times) {
			$res = $mAnniversaryInvite->field('id, invite_user_id, order_status, order_id')->limit($start * 1000, $size)->select();
			if ($res) {
				foreach($res as $item) {
					echo 'invite_user_id:' . $item['invite_user_id'].' id:' . $item['id'] . " order_status:" .$item['order_status'] ." order_id:". $item['order_id'] . "\r\n";
				}
			} else {
				break;
			}
			unset($res);
			$start++;
			usleep(1000);
		}
	}

	/**
	 * 增加打钱测试账号，发放0.01
	 */
	public function testAccount()
	{
		echo "ok";exit;
		try {
			$mAnniversaryWithdraw = new AnniversaryWithdraw();
			#将可提现金额记录入AnniversaryWithdraw
			$data = array(
				'invite_user_id' => 112029068019154961,
	            'status' => 0,
	            'amount' => 0.01
			);
	        $insert_result = $mAnniversaryWithdraw->insert($data);
	        if($insert_result) {
	        	echo "success!";
	        } else {
	        	echo "failed!";
	        }
        } catch (\Exception $exception) {
           	var_dump($exception->getMessage());
        }
	}

	/**
	 * 统计需要提现的用户以及相关提现金额信息
	 */
	public function statisticsWithdraw()
	{
		echo "ok";exit;
		#不在活动时间内，直接返回失败
        $end_time = config('zhuanzhuan_anniversary_end_time');
		if (time() <= strtotime($end_time)) {
			echo 'ok';exit;
		}

		$mAnniversaryInvite = new AnniversaryInvite();
		$mAnniversaryWithdraw = new AnniversaryWithdraw();
		$max_times = 50000;
		$start = 0;
		$success = 0;
		$size = 1000;
		while ($start < $max_times) {
			$res = $mAnniversaryInvite->Distinct(true)->field('invite_user_id')->limit($start * 1000, $size)
				->select();
			if ($res) {
				foreach ($res as $item) {
					#先判断提现数据表中是否已经存在该用户数据
					$res_withdraw_id = $mAnniversaryWithdraw->where('invite_user_id', $item['invite_user_id'])->value('id');
					if ($res_withdraw_id) {
						#该用户已经入提现数据表
						continue;
					}

					#查询对应用户下邀请的注册用户相关数据
					$invite_result = $mAnniversaryInvite->where('invite_user_id', $item['invite_user_id'])
						->order('register_time','asc')
						->field('invite_user_id, invited_user_id, order_status')
                		->select();
                	if ($invite_result) {
                		$i = 0;
                		$amount = 0; #可提现金额
                		$card_group_amount_status = 0; #标记每3个一组的是否具备可提现条件，0不可1可
                		#循环邀请的注册用户，计算出可提现金额
                		foreach ($invite_result as $invate_item) {
                			$i++;
                			if ($invate_item['order_status'] == 1) {
								$card_group_amount_status = 1;
                			}
                			if ($i % 3 == 0) {
                				if ($card_group_amount_status) {
                					$amount += 8.8;
                				}
                				$card_group_amount_status = 0;
                			}
                		}

                		#将可提现金额记录入AnniversaryWithdraw
                		$data = array(
                			'invite_user_id' => $item['invite_user_id'],
			                'status' => 0,
			                'amount' => $amount
                		);
			            $insert_result = $mAnniversaryWithdraw->insert($data);
			            if ($insert_result) {
			            	$success++;
			            }
			            unset($data);
                	}
                	unset($res_withdraw);
                	unset($invite_result);
					usleep(100);
				}
			} else {
				break;
			}
			unset($res);
			$start++;
			usleep(1000);
		}

		echo "finish success:" . $success;
	}

	/**
	 * 发送奖励接口
	 */
	public function withdraw()
	{
		echo "ok";exit;
		#不在活动时间内，直接返回失败
        $end_time = config('zhuanzhuan_anniversary_end_time');
		if (time() <= strtotime($end_time)) {
			echo 'ok';exit;
		}

		echo date("Y-m-d H:i:s") . " 开始发送奖励\r\n";
		#查看是否已经存在该记录
		$mAnniversaryWithdraw = new AnniversaryWithdraw();
		$max_id = $mAnniversaryWithdraw->order('id','desc')->limit(1)->value('id');
        if (empty($max_id)){
        	echo "获取anniversary_withdraw最大ID失败!\r\n";
        	exit;
        }

		#次数异常次数，则停止
		$exception_time = 0;

		#成功次数
		$success = 0;
		$total_amount = 0;

		#发送奖励
		for ($i = 1; $i <= $max_id; $i++) {
			try{
				$res = $mAnniversaryWithdraw->where('id', $i)->field('invite_user_id, status, amount')->select();
				if ($res && $res[0]['status'] == 0) {
					#接口发送
					$item = $res[0];
					if ($item['amount'] > 0) {
						$params_withdraw = array(
				            'userId' => $item['invite_user_id'],
				            'giveAmount' => $item['amount'],
				            'activityType' => 12,
				            'is_post' => 1
				        );
				        echo '发送Api2_5_0/activity/giveTransferBalance奖励参数：' . json_encode($params_withdraw) . "\r\n";
				        $withdraw_info = api('Api2_5_0/activity/giveTransferBalance', $params_withdraw, false, config('DOMAIN_API_TJJ_SERVICE'));
				        echo '主流程返回：' . json_encode($withdraw_info) . "\r\n";
				        if ($withdraw_info && isset($withdraw_info['result']) && $withdraw_info['result'] == 1) {
				        	#发送成功
				        	$update = $mAnniversaryWithdraw->where(array('invite_user_id' => $item['invite_user_id']))
				                ->update(array('status' => 1));
							if ($update) {
								$success++;
								$total_amount += $item['amount'];
								echo 'invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount']." success\r\n";
							} else {
								echo 'invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount']." update failed\r\n";
							}
				        } else {
				        	echo '主流程结果异常：' . json_encode($withdraw_info).' 参数：' . json_encode($params_withdraw)."\r\n";
				        }

				        unset($params_withdraw);
				        unset($withdraw_info);
					} else {
						echo '金额小于或等于0，invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount']."\r\n";
					}
				}
				$exception_time = 0;
				unset($res);
			} catch (\Exception $exception) {
				echo 'Exception:' . json_encode($exception->getMessage()) . "\r\n";
				$exception_time++;
				if($exception_time > 20) {
					echo 'Exception次数太多，停止发送, 异常次数:' . $exception_time . "\r\n";
					exit;
				}
	        }
			usleep(1000);
		}
		echo 'finish success:' . $success . " totalAmount:" . $total_amount . "\r\n";
	}

	public function withdrawTest()
	{
		echo "ok";exit;
		#不在活动时间内，直接返回失败
        $end_time = config('zhuanzhuan_anniversary_end_time');
		if (time() <= strtotime($end_time)) {
			echo 'ok';exit;
		}

		echo date("Y-m-d H:i:s") . " 开始发送奖励\r\n";
		#查看是否已经存在该记录
		$mAnniversaryWithdraw = new AnniversaryWithdraw();
		$max_id = $mAnniversaryWithdraw->order('id','desc')->limit(1)->value('id');
        if (empty($max_id)){
        	echo "获取anniversary_withdraw最大ID失败!\r\n";
        	exit;
        }

		#次数异常次数，则停止
		$exception_time = 0;

		#成功次数
		$success = 0;
		$total_amount = 0;

		#发送奖励
		for ($i = 1; $i <= $max_id; $i++) {
			try{
				$res = $mAnniversaryWithdraw->where('id', $i)->field('invite_user_id, status, amount')->select();
				if ($res && $res[0]['status'] == 0) {
					$item = $res[0];
					#先测试只对这一个账号打款
					if($item['invite_user_id'] != 112029068019154961) {
						continue;
					}

					#接口发送
					if ($item['amount'] > 0) {
						$params_withdraw = array(
				            'userId' => $item['invite_user_id'],
				            'giveAmount' => $item['amount'],
				            'activityType' => 12,
				            'is_post' => 1
				        );
				        echo '发送Api2_5_0/activity/giveTransferBalance奖励参数：' . json_encode($params_withdraw) . "\r\n";
				        $withdraw_info = api('Api2_5_0/activity/giveTransferBalance', $params_withdraw, false, config('DOMAIN_API_TJJ_SERVICE'));
				        echo '主流程返回：' . json_encode($withdraw_info) . "\r\n";
				        if ($withdraw_info && isset($withdraw_info['result']) && $withdraw_info['result'] == 1) {
				        	#发送成功
				        	$update = $mAnniversaryWithdraw->where(array('invite_user_id' => $item['invite_user_id']))
				                ->update(array('status' => 1));
							if ($update) {
								$success++;
								$total_amount += $item['amount'];
								echo 'invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount']." success\r\n";
							} else {
								echo 'invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount']." update failed\r\n";
							}
				        } else {
				        	echo '主流程结果异常：' . json_encode($withdraw_info).' 参数：' . json_encode($params_withdraw)."\r\n";
				        }

				        unset($params_withdraw);
				        unset($withdraw_info);
					} else {
						echo '金额小于或等于0，invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount']."\r\n";
					}
				}
				$exception_time = 0;
				unset($res);
			} catch (\Exception $exception) {
				echo 'Exception:' . json_encode($exception->getMessage()) . "\r\n";
				$exception_time++;
				if($exception_time > 20) {
					echo 'Exception次数太多，停止发送, 异常次数:' . $exception_time . "\r\n";
					exit;
				}
	        }
			usleep(1000);
		}
		echo 'finish success:' . $success . " totalAmount:" . $total_amount . "\r\n";
	}

	/**
	 * 发送奖励接口
	 */
	public function withdrawView()
	{
		echo "ok";exit;
		#不在活动时间内，直接返回失败
        $end_time = config('zhuanzhuan_anniversary_end_time');
		if (time() <= strtotime($end_time)) {
			echo 'ok';exit;
		}

		echo date("Y-m-d H:i:s") . " withdrawView开始发送奖励\r\n";
		#查看是否已经存在该记录
		$mAnniversaryWithdraw = new AnniversaryWithdraw();
		$max_id = $mAnniversaryWithdraw->order('id','desc')->limit(1)->value('id');
        if (empty($max_id)){
        	echo "withdrawView获取anniversary_withdraw最大ID失败!\r\n";
        	exit;
        }

		#次数异常次数，则停止
		$exception_time = 0;

		#成功次数
		$success = 0;
		$totalAmount = 0;

		#发送奖励
		for ($i = 1; $i <= $max_id; $i++) {
			try{
				$res = $mAnniversaryWithdraw->where('id', $i)->field('invite_user_id, status, amount')->select();
				if ($res && $res[0]['status'] == 0) {
					#接口发送
					$item = $res[0];
					if ($item['amount'] > 0) {
						$params_withdraw = array(
				            'userId' => $item['invite_user_id'],
				            'giveAmount' => $item['amount'],
				            'activityType' => 12,
				            'is_post' => 1
				        );
				        $success++;
				        $totalAmount += $item['amount'];

				        echo 'withdrawView userId:' . $params_withdraw['userId'] . ' amount:' . $params_withdraw['giveAmount'] . "\r\n";
				        unset($params_withdraw);
					} else {
						echo '金额小于或等于0，invite_user_id:' . $item['invite_user_id'].' amount:' . $item['amount'] . "\r\n";
					}
				}
				$exception_time = 0;
				unset($res);
			} catch (\Exception $exception) {
				echo 'withdrawView Exception:' . json_encode($exception->getMessage()) . "\r\n";
				$exception_time++;
				if($exception_time > 20) {
					echo 'withdrawView Exception次数太多，停止发送, 异常次数:' . $exception_time . "\r\n";
					exit;
				}
	        }
			usleep(100);
		}
		echo 'withdrawView finish success:' . $success . " totalAmount:" . $totalAmount . "\r\n";
	}

    /**
	 * 人脉PK-文案：XX用户参与淘集集集卡活动领取了XXX元
	 * 金额以8.8倍数随机选取，取值范围【1*8.8-N*8.8】N：1-100
	 */
    public function getAwardInfo()
    {
        $rand_id = intval(mt_rand(100000, 5000000) / 10000);
        #redis key,假数据500份里面随机
    	$key = $this::KEY . 'ANNI_AWARD_INFO_' . $rand_id;
    	$times = 0;
    	#并发情况下，重试5次
    	while($times < 5) {
    		$times++;
    		$data = $this->handler->get($key);
	        if (empty($data)) {
	            #生成排行值
		    	$data = [];
		        $userIconArr = array_rand(range(1, 654), $this->award_num);
		        $nickArr = config("nickname");
		        for ($i = 0; $i < $this->award_num; $i++) {
		            $data[] = array(
		            	'user' => $nickArr[$userIconArr[$i]],
		                'userIcon' => 'http://' . config('DOMAIN_TJJ_UPLOAD') . '/group/userIcon/1' . $userIconArr[$i] . '.jpg',
		                'amount' => intval(mt_rand(10000, 1000000) /10000) * 88 / 10, #已提现n元
		            );
		        }

				if ($this->handler->set($key, json_encode($data), array('nx', 'ex' => 86400 + mt_rand(1, 3600)))) {
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
     * 获取一定数量的昵称
     */
    public function getNicknames($num=10)
    {
		$nickname_arr = config('nickname');
		shuffle($nickname_arr);
		return array_slice($nickname_arr, 0, $num);
    }
}