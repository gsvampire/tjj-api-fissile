<?php
/**
 * 周年庆商品
 * Date: 2019/7/1
 * Time: 16:18
 */
namespace app\anniversary\controller;
use think\cache\driver\Redis;
class Goods extends Common
{
    const MODEL_NAME = 'Goods';

    #########################redisKEY###############################################
    const KEY = "ANNIVERSARY-GOODS-ACTIVITY_ID-";
    
    public function _initialize()
    {
        $request = $this->request->param();
        $this->filter($request);
        try{
            $this->redis = new Redis(config('redis'));
            $this->handler = $this->redis->handler();
        }catch(Exception $e){
            $this->apiLog($_REQUEST,$e->getMessage(),$_SERVER);
        }
    }

    /**
     * 通用商品列表
     * @param $activity_id
     * @param $coordinate
     */
    public function goods_list($activity_id, $coordinate)
    {
        $request = $this->request->param();
        if (!empty($request['user_id'])) {
            $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
            (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        }

        $key = $this::KEY . $activity_id . "-GOODS-COORDINATE-" . $coordinate;
        $redis_result = $this->redis->get($key);
        if (empty($redis_result)) {
            //从表中获取数据
            try{
                $goods_list = model($this::MODEL_NAME)->goods_list($activity_id, $coordinate);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message);
                $this->returnError(-1, $e->getMessage());
            }

            if (!empty($goods_list)) {
                //将数组转为以coordinate为key的对象方便前端处理数据
                $i = 0;
                foreach ($goods_list as $k => $v) {
                    if (!empty($v['goods_id'])) {
                        if (!empty($v['supplement'])) {
                            //解码补充字段
                            $v = array_merge($v, json_decode($v['supplement'], true));
                            $i++;
                        }
                        unset($v['supplement']);
                        $goods_info['goods'][$v['goods_id']] = empty($v) ? [] : $v;
                    }
                }
                $goods_info['status'] = empty($i) ? 0 : 1;
                if (!empty($goods_info)) {
                    $this->redis->set($key, $goods_info, $this::EXPIRATION);
                }
            }
        } else {
            $goods_info = $redis_result;
        }

        if (!empty($goods_info['goods'])) {
            $data = array(
                'goods_info' => $goods_info['goods'],
                'goods_ids' => implode(",", array_keys($goods_info['goods'])),
                'status' => empty($goods_info['status']) ? 0 : 1,
            );
            $this->interlayer(['result' => 1, 'data' => $data]);
        } else {
            $this->interlayer(['result' => '-61', 'data' => []]);
        }
    }

    /**
     * 通用资源位列表
     * @param $activity_id
     * @param $coordinate   ##支持批量，以英文逗号分割
     */
    public function link_list($activity_id, $coordinate)
    {
        $request = $this->request->param();
        if (!empty($request['user_id'])) {
            $user_check = $this->checkToken($request['user_id'], $request['token'], $request['uuid']);
            (isset($user_check['result']) && $user_check['result'] < 1) ? $this->interlayer($user_check, true) : true;
        }

        $key = $this::KEY . $activity_id . "-LINK-COORDINATE-" . $coordinate;
        $redis_result = $this->redis->get($key);
        if (empty($redis_result)) {
            //判断是否为批量获取页面资源位，type=1：批量，type=2：单个
            $type = strstr(",", $coordinate) ? 1 : 2;

            //从表中获取数据
            try{
                $link_list = model($this::MODEL_NAME)->link_list($activity_id, $coordinate, $type);
            }catch(Exception $e) {
                $message = $e->getMessage();
                $this->apiLog($request, $message);
                $this->returnError(-1, $e->getMessage());
            }

            if (!empty($link_list)) {
                //将数组转为以coordinate为key的对象方便前端处理数据
                foreach ($link_list as $k => $v) {
                    if (!empty($v['coordinate'])) {
                        if (!empty($v['supplement'])) {
                            //解码补充字段
                            $v = array_merge($v, json_decode($v['supplement'], true));
                        }
                        unset($v['supplement']);
                        if (empty($link_info[$v['coordinate']])) {
                            $link_info[$v['coordinate']][0] = empty($v) ? [] : $v;
                        } else {
                            array_push($link_info[$v['coordinate']], $v);
                        }
                    }
                }
                if (!empty($link_info)) {
                    $this->redis->set($key, $link_info, $this::EXPIRATION);
                }
            } else {
                $link_info = [];
            }
        } else {
            $link_info = $redis_result;
        }

        $data = array(
            'link_info' => empty($link_info) ? [] : $link_info,
        );
        $this->interlayer(['result' => 1, 'data' => $data]);
    }
}