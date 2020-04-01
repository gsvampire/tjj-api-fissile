<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-08-01
 * Time: 13:53
 */

//统一分发层
namespace app\zz\controller;

use think\Controller;
use app\zz\model\MRedis;
use think\Log;
use think\Request;

class UnifyOut extends Controller
{

    /**
     * 统一分发 入redis
     */
    public function unifyreg()
    {
        //------test数据--------
//        $testarr=[
//            's_user_id'=>'111111',
//            'user_id'=>'222222',
//            'activity_type'=>15,
//            'create_time'=>time(),
//            'is_reg'=>1,
//        ];
//        $mInfo=json_encode($testarr);
        //------test数据--------
        $mInfo = file_get_contents("php://input");
        try {
            $pushUrl = config('unify');
            $times = date('Y-m-d H:i:s');
            if (empty($pushUrl)) {
                Log::info('活动统一注册回调数据:在时间：' . $times . '没有找到要推送的配置信息');
                echo 'ok';
                exit;
            };
            //回调数据只接受配置文件中的数据
            $validPushUrl = [];
            foreach ($pushUrl as $k => $v) {
                if (isset($v['is_push']) && !empty($v['is_push'])) {
                    $validPushUrl[] = $v['activity_type'];
                }
            }
            if (empty($validPushUrl)) {
                Log::info('活动统一注册回调数据:在时间：' . $times . '没有找到要配置is_push=>true的信息');
                echo 'ok';
                exit;
            }
            $configType = array_flip($validPushUrl);

            $aInfo = json_decode($mInfo, true);
            if (!isset($aInfo['activity_type']) || empty($aInfo['activity_type'])) {
                Log::info('活动统一注册回调数据:时间：' . $times . ' activity_type为空');
                echo 'ok';
                exit;
            }
            if (!array_key_exists($aInfo['activity_type'], $configType)) {
                Log::info('活动统一注册回调数据:在时间:' . $times . ' 回调的数据找不跟配置的activity_type不匹配');
                echo 'ok';
                exit;
            }
            Log::info('活动统一注册回调数据:统一分发注册数据,时间：' . $times . '数据为：' . $mInfo);

            $redis = new MRedis();
            $lpushInfo = $redis->lpushactivityreg($aInfo);
            if (!empty($lpushInfo)) {
                Log::info('活动统一注册回调数据:时间：' . $times . ',插入redis成功,数据为：' . json_encode($aInfo));
                echo 'ok';
                exit;
            } else {
                Log::info('活动统一注册回调数据:时间：' . $times . ',插入redis发生异常,数据为：' . json_encode($aInfo));
                echo 'error';
                exit;
            }

        } catch (\Exception $exception) {
            Log::info('活动统一注册回调数据:系统发生异常，异常时间为：' . $times . '异常数据为:' . $mInfo);
            echo 'error';
            exit;
        }

    }


    /**
     * @param Request $request
     * @return array
     * 被动 单个获取注册数据
     */
//    public function singlereg(Request $request)
//    {
//       try{
//           $activityId=intval(trim($request->param('activity_type')));
//           if(empty($activityId)) return returnJson([],'活动类型不合法',-1);
//           $redis=new MRedis();
//           $res=$redis->rpopactivityreg($activityId);
//           if(!empty($res)) return returnJson($res,'请求成功',1);
//           return returnJson([],'暂无数据',-1);
//       }catch (\Exception $exception){
//           Log::info('系统发生异常，异常信息为：'.$exception->getMessage());
//           return returnJson([],'系统发生异常',-1);
//       }
//
//    }


    /**
     * @param Request $request
     * @return array
     * 被动 批量获取注册数据
     */
//    public function batchreg(Request $request)
//    {
//        try{
//            $activityId=intval(trim($request->param('activity_type')));
//            $len=intval(trim($request->param('length')));
//            if(empty($activityId)) return returnJson([],'活动类型不合法',-1);
//            $redis=new MRedis();
//            $res=$redis->batchpopactivityreg($activityId,$len);
//            if(!empty($res)) return returnJson($res,'请求成功',1);
//            return returnJson([],'暂无数据',-1);
//        }catch (\Exception $exception){
//            Log::info('系统发生异常，异常信息为：'.$exception->getMessage());
//            return returnJson([],'系统发生异常',-1);
//        }
//    }


    /**
     * @return array|string
     * @throws \ReflectionException
     * 分发数据
     */
    public function unifyregpush()
    {
        try{
            $pushUrl = config('unify');
            $times = date('Y-m-d H:i:s');
            if (empty($pushUrl)) {
                Log::info('活动注册分发:在时间 ' . $times . '没有找到推送的配置信息');
                return returnJson([], '没有推送地址', 1);
            }
            $validPushUrl = [];
            //筛选配置为要推送的信息
            foreach ($pushUrl as $k => $v) {
                if (isset($v['is_push']) && !empty($v['is_push'])) {
                    $validPushUrl[$k]['class'] = $v['class'];
                    $validPushUrl[$k]['method'] = $v['method'];
                    $validPushUrl[$k]['url'] = $k;
                    $validPushUrl[$k]['activity_type'] = $v['activity_type'];
                    $validPushUrl[$k]['length'] = $v['length'];
                }
            }
            if (empty($validPushUrl)) {
                Log::info('活动注册分发:在时间 ' . $times . '没有找到满足条件的推送数据');
                return returnJson([], '没有要推送数据', 1);
            }
            $validPushUrl = array_values($validPushUrl);

            $redis = new MRedis();
            foreach ($validPushUrl as $vk => $vv) {
                $activityLen = $redis->llenactivityreg($vv['activity_type']);
                if (empty($activityLen)) {
                    Log::info('活动注册分发:在时间 ' . $times . '要推送的数据为空,活动类型为:'.$vv['activity_type']);
                    continue;
                }

                $pushclass = new \ReflectionClass($vv['class']);

                if ($pushclass->hasMethod($vv['method'])) {
                    $param = $redis->batchpopactivityreg($vv['activity_type'], $vv['length']);
                    Log::info('活动注册分发:在时间 ' . $times . '分发的活动类型:' . $vv['activity_type'] . ':要分发的数据:' . json_encode($param));
                    $response = (new $vv['class'])->{$vv['method']}($param);
                    if ($response == 'ok') {
                        Log::info('活动注册分发:在时间 ' . $times . '分发的活动类型:' . $vv['activity_type'] . ':分发成功的数据:' . json_encode($param));

                        $resinfo=$redis->batchlistactivityreg($vv['activity_type'], $vv['length']);
                        if(empty($resinfo)){
                            Log::info('活动注册分发:在时间 ' . $times . '分发的活动类型:' . $vv['activity_type'] . '分发成功后,删除失败的数据:' . json_encode($param));
                        }else{
                            Log::info('活动注册分发:在时间 ' . $times . '分发的活动类型:' . $vv['activity_type'] . '分发成功后,删除成功的数据:' . json_encode($param));
                        }
                    }
                }else{
                    Log::info('活动注册分发:在时间 ' . $times . '没有该活动类型的对应方法,活动类型为:' . $vv['activity_type']);
                }
            }
            return returnJson([], '请求成功', 1);
        }catch (\Exception $exception){
            return returnJson([], '系统异常', -1);
        }
    }

}