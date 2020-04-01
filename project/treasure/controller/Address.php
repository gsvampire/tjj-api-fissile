<?php
/**
 * 地址模块
 * Date: 2019/9/21
 * Time: 14:56
 */
namespace app\treasure\controller;
use think\cache\driver\Redis;
use app\treasure\model\WinAddress;
use think\Log;
class Address extends Common
{
    const MODEL_NAME = "WinAddress";
    #########################redisKEY###############################################
    const KEY = "TREASURE-ADDRESS-";

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
     * 获奖人地址展示
     * $user_id : 用户id
     * $activity_id : 活动id
     */
    public function address_show()
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

            //获取地址信息
            $address_info = WinAddress::getAddress($request['user_id'], $request['activity_id']);
            if (empty($address_info)) {
                $this->interlayer(['result' => '-11023']);
            } else {
                $result = array(
                    'result' => 1,
                    'data' => $address_info
                );
                $this->interlayer($result);
            }
        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[AddressController:address_show]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return ['result' => '-11029'];
        }
    }

    /**
     * 地址上传
     * $user_id : 用户id
     * $activity_id : 活动id
     * $province : 省份
     * $city : 城市
     * $district : 地区
     * $address : 详细地址
     * $mobile : 电话
     * $consignee : 收件人
     */
    public function address_upload()
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

            //地址信息不完整
            if (empty($request['consignee']) || empty($request['province']) || empty($request['city']) || empty($request['district']) || empty($request['address']) || empty($request['mobile'])) {
                $this->interlayer(['result' => '-11024']);
            }

            //手机号码格式有误
            if (!preg_match("/^1([38][0-9]|4[579]|5[0-3,5-9]|6[6]|7[0135678]|9[89])\d{8}$/", $request['mobile'])) {
                $this->interlayer(['result' => '-11025']);
            }

            //获取地址信息
            $address_info = WinAddress::getActivityAddress($request['activity_id']);
            if (empty($address_info)) {
                $this->interlayer(['result' => '-11033']);
            }

            //是否已填写过地址
            if (!empty($address_info['address_id'])) {
                $code = $address_info['user_id'] == $request['user_id'] ? '-11026' : '-11034';
                $this->interlayer(['result' => $code]);
            }

            //地址录入
            $data = array(
                'activity_id' => $request['activity_id'],
                'user_id' => $request['user_id'],
                'province' => $request['province'],
                'city' => $request['city'],
                'district' => $request['district'],
                'address' => $request['address'],
                'consignee' => $request['consignee'],
                'mobile' => $request['mobile'],
            );
            $upload_result = model($this::MODEL_NAME)->address_upload($data);

            if ($upload_result) {
                $this->interlayer(['result' => 1]);
            } else {
                $this->interlayer(['result' => '-11027']);
            }
        } catch (\Exception $e) {
            Log::info("[夺宝活动]-[AddressController:address_upload]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            return ['result' => '-11029'];
        }
    }
}