<?php
namespace app\v1_2_0\controller;

/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/3/21
 * Time: 20:34
 */
use app\v1_0_0\controller\Common;
use think\cache\driver\Redis;
use weixin\WxPay;

class Wx extends Common{

    public function __construct()
    {
        parent::__construct();
        $this->redis=new Redis(config('redis'));
        $this->handler=$this->redis->handler();
        $this->access=new WxPay('wxTjj');
    }


    public function checkOrder($order_no,$user_id,$token,$uuid)
    {
        $params['order_no']=$order_no;
        $params['user_id']=$user_id;
        $params['payment_id']=3;
        $params['token']=$token;
        $params['uuid']=$uuid;
        $order=api('wap/Order/orderPay',$params);

        //筛选订单状态
        $order['result']!=1?$this->returnError(-22,$order['message']):'';
        $order['order_amount']<=0?$this->returnError(-30):'';
        return $order;
    }

    //$trade_type='MWEB'  'JSAPI'  'NATIVE' 扫码支付
    public function unifiedorder($user_id,$token,$uuid,$order_no,$goods_name,$trade_type='JSAPI')
    {
        $userCheck=$this->checkToken($user_id,$token,$uuid);
        $userCheck['result']!=1?$this->returnError(-33,$userCheck['message']):'';

        $goods_name=urldecode($goods_name);
        $goods_name=strlen($goods_name)>70? mb_substr($goods_name, 0, 25, 'utf-8').'...': $goods_name;
        empty($order_no)?$this->returnError(-21):'';
        $order=$this->checkOrder($order_no,$user_id,$token,$uuid);
        //统一下单
        $postData = [
            'body' => $goods_name,
            'out_trade_no' => $order_no,
            'spbill_create_ip' => $this->access->ip,
            'total_fee' => bcmul($order['order_amount'],100,0),
            'time_start' => date("YmdHis", time()),
            'time_expire' => date("YmdHis", time()+1800),
            'trade_type' => $trade_type,
            'notify_url' => $this->access->notify_url.'/user_id/'.$user_id,//异步回调
        ];
        $trade_type == 'MWEB'? $postData['scene_info'] = json_encode(["h5_info" => ["type" => "Wap", "wap_url" => protocol() . $_SERVER['HTTP_HOST'], "wap_name" => "淘集集"]]):'';
        $trade_type == 'JSAPI'?$postData['openid'] = $this->access->getOpenId():'';
        $trade_type == 'NATIVE'?$postData['product_id'] = '111':'';
        $res = $this->trade($postData,'unifiedorder');//统一下单
        //mweb_url为拉起微信支付收银台的中间页面
        if ($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'SUCCESS') {
            if($trade_type == 'JSAPI'){
                $nonceStr=$this->access->getNonceStr();
                $timestamp=time();
                $package='prepay_id='.$res['prepay_id'];
                $appId=$this->access->appId;
                $signArr=[
                    'package'=>$package,
                    'signType'=>'MD5',
                    'nonceStr'=>$nonceStr,
                    'timeStamp'=>$timestamp,
                    'appId'=>$appId
                ];
                $paySign=$this->access->getSign($signArr);

                Header('location:'.protocol().$_SERVER['HTTP_HOST'].'/clock/view/v1_0_0/paying/signType/MD5/package/'.$package.'/nonceStr/'.$nonceStr.'/timeStamp/'.$timestamp.'/appId/'.$appId.'/paySign/'.$paySign.'/order_no/'.$order_no);
                return;
            }else{
                $redirect_url=urlencode(empty($_POST['redirect_url'])? $_GET['redirect_url']: $_POST['redirect_url']);
                $data['data']=['mweb_url'=>$res['mweb_url'].'&redirect_url='.$redirect_url];
            }
        } else {
            if ($res['return_code'] == 'SUCCESS' && $res['result_code'] == 'FAIL') {
                $data = [
                    'code' => $res['err_code'],
                    'message' => $res['err_code_des'],
                ];
            } else {
                $data = [
                    'code' => $res['return_code'],
                    'message' => $res['return_msg'],
                ];
            }
        }
        echo json_encode($data);
    }

    //封装微信交易方法
    public function trade($postData,$operate)
    {
        $this->access->postData=$postData;
        return $this->access->doUrl($operate);
    }

    public function paySuccess()
    {
        $order_no = $_GET['order_no'];
        $key = 'WXPAY_TJJ_NOTIFY:' . date('Y-m-d') . '--' . $order_no;

        $this->access->postData = ['out_trade_no' => $order_no];
        $res = $this->access->doUrl('orderquery');

        if (S($key) == 1 || $res['trade_state']=='SUCCESS') {
            $this->display('paySuccess');
        } else {
            $this->display('payFail');
        }

    }


    /**
     * 微信异步回调，修改支付状态
     */
    public function getNotifyData()
    {
        $KEY = 'FISSION_TJJ_WXPAY_NOTIFY:' . date('Y-m-d');
        $this->handler->expire($KEY, 7200);//缓存两个小时
        $this->handler->sadd($KEY, $GLOBALS['HTTP_RAW_POST_DATA']);
        //获取通知的数据
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        if (empty($xml)) {
            return false;
        }
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        getCurl('Mkfpay/wxPayLog', $data);

        if ($data['return_code'] == 'FAIL') {
            return false;
        }
        if ($data['result_code'] == 'SUCCESS' && $data['return_code'] == 'SUCCESS') {
            //校验签名
            ksort($data);
            $string = "";
            foreach ($data as $k => $v) {
                if ($k != 'sign') {
                    $string .= $k . "=" . $v . "&";
                }
            }
            $mchKey = $this->access->key;
            $stringSignTemp = $string . 'key=' . $mchKey;
            $sign = strtoupper(MD5($stringSignTemp));
            if ($sign == $data['sign']) {
                //1.支付成功 回调接口
                $userId=input('param.user_id');
                if(!$userId){
                    $where['order_no']=$data['out_trade_no'];
                    $userId=M('order')->where($where)->getField('user_id');
                }

                empty($userId)?$this->returnError(-28):'';
                $trade_type= $data['trade_type']=='MWEB'?'weixinwap':'weixin';
                $params['user_id']=$userId;
                $params['order_no']=$data['out_trade_no'];
                $params['real_out_trade_no']=$data['out_trade_no'];
                $params['billno']=$data['transaction_id'];
                $params['payamount']=$data['total_fee']/100;
                $params['paytime']=strtotime($data['time_end']);//转为时间戳
                $params['paycode']=$trade_type;//$data['trade_type'];
                $params['payname']=$data['openid'];
                $params['seller_id']=$data['mch_id'];
                $params['payno']=$data['out_trade_no'];

                $orderState=pay_api('Order/paySuccess',$params);

                $orderState['result']!=1?$this->returnError(-29):'';
                //2.响应微信
                $xml = "<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>";
                echo $xml;
            }
        }
    }
}