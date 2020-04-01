<?php


namespace app\treasure\controller;


use app\treasure\service\IndexService;
use app\treasure\service\RedisService;
use app\treasure\service\TreasureticketService;
use think\Log;

class Callback extends Common
{
    const RECHARGE_ORDER_RESULT_SUCCESS = 1; //成功
    const RECHARGE_ORDER_RESULT_FAIL = 2; //失败

    const BUSINESS_ORDER_DELETE_ZERO = 0;
    const BUSINESS_ORDER_COIN_ONE = 1;

    const WIN_TICKET_TYPE_RECHARGE = 4; //充值送券
    const WIN_TICKET_TYPE_BUSINESS = 5; //购买商品送券

    const TICKET_SIGN_NEED = 1; //发券需要签名
    const TICKET_SIGN_NO_NEED = 2; //发券不需要签名

    /**
     * 解析获取请求参数
     * @return mixed
     */
    protected function requestParams()
    {
        $content = file_get_contents('php://input');
        Log::info("[夺宝活动回调参数]:" . $content);
        $params = json_decode($content, true);
        return $params;
    }

    protected function returnOk()
    {
        die("ok");
    }

    protected function redisService()
    {
        return new RedisService();
    }

    /**
     * 充值订单支付成功回调
     */
    public function recharge_order()
    {
        $request_params = $this->requestParams();
        $order_info = $request_params;
        $user_id = $order_info['user_id'];
        $sub_order_id = $order_info['sub_order_id'];
        $order_id = explode('-', $sub_order_id)[0];
        $result = $order_info['result'];
        $amount = $order_info['payment_amount'];
        if ($result == self::RECHARGE_ORDER_RESULT_SUCCESS && $user_id && $amount && $order_id) {
            $this->order($user_id, $order_id, $amount, self::WIN_TICKET_TYPE_RECHARGE);
        }
        $this->returnOk();
    }

    /**
     * 商业订单支付成功回调
     */
    public function business_order()
    {
        $request_params = $this->requestParams();
        $order_info = $request_params['order'];
        $user_id = $order_info['user_id'];
        $order_id = $order_info['order_id'];
        $amount = $order_info['amount'];
        $coin = $order_info['coin'];
        $is_delete = $order_info['is_delete'];
        if ($user_id && $order_id && $amount && $coin != self::BUSINESS_ORDER_COIN_ONE && $is_delete == self::BUSINESS_ORDER_DELETE_ZERO) {
            $this->order($user_id, $order_id, $amount, self::WIN_TICKET_TYPE_BUSINESS);
        }
        $this->returnOk();
    }

    /**
     * 订单支付成功回调
     * @param $user_id
     * @param $order_id
     * @param $amount
     * @param $type
     * @return bool
     */
    protected function order($user_id, $order_id, $amount, $type)
    {
        $key = "pay_order:callback:order_id:{$order_id}";
        $redis_server = $this->redisService();
        if (!$redis_server->get($key)) {
            $ticket_list_model = model(TreasureticketService::TICKET_MODEL_NAME);
            $redis_server->set($key, time(), 3600 * 24);
            $tickets = $ticket_list_model->where(['order_id' => $order_id])->select();
            if (!$tickets) {
                // 根据订单金额发送夺宝券
                $ticket_num = floor($amount / 2);
                $treasure_ticket_service = new TreasureticketService();
                $result = $treasure_ticket_service->get($type, $ticket_num, $user_id, self::TICKET_SIGN_NEED, ['order_id' => $order_id]);
                Log::info("[夺宝活动下单成功后发券]-[first]:" . json_encode($result, JSON_UNESCAPED_UNICODE));
                return true;
            }
        }
        Log::info("[夺宝活动下单成功后发券]-[repeat]:{$order_id}");
        return false;
    }

    /**
     * 订单退款回调
     */
    public function refund()
    {
        $request_params = $this->requestParams();
        $treasure_ticket_service = new TreasureticketService();
        // 订单发放的夺宝券失效
        $order_id = $request_params['order_id'];
        $user_id = $request_params['user_id'];
        if ($order_id) {
            $key = "refund_order:callback:order_id:{$order_id}";
            $redis_server = $this->redisService();
            if (!$redis_server->get($key)) {
                $treasure_ticket_service->take_back_ticket($order_id);
                IndexService::clearTicketAccount($user_id);
                $redis_server->set($key, time(), 3600 * 24);
                Log::info("[夺宝活动退款、退货后收券和幸运号]-[first]:{$order_id}");
            } else {
                Log::info("[夺宝活动退款、退货后收券和幸运号]-[repeat]:{$order_id}");
            }
        }
        $this->returnOk();
    }
}