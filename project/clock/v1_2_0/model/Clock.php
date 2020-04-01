<?php
/**
 * 打卡项目model层
 */

namespace app\v1_2_0\model;
use think\Db;
class Clock extends Common
{
    /**
     * 用户打卡商品下单数据查询
     * @param $user_id
     * @return 一维数组
     */
    public function user_pay_log($user_id){
        $payModel = $this->dataModel($this::CLOCK_PAY_LOG);
        $field = "user_id,goods_id,order_no,pay_time,day_num,goods_info";
        $where = array(
            'user_id' => $user_id,
        );
        $result = $payModel->field($field)->where($where)->find();
        return $result;
    }

    /**
     * 打卡分类列表
     * @return 二维数组
     */
    public function cate_list(){
        $cateModel = $this->dataModel($this::CATE_LIST);
        $field = "cid,sort";
        $where = array(
            'sort' => ['gt',0],
        );
        $result = $cateModel->field($field)->where($where)->order('sort')->select();
        return $result;
    }

    /**
     * 打卡商品列表
     * @param $cate_id
     * @param $sort_type
     * @param $page
     * @return 二维数组
     */
    public function goods_list($cate_id,$sort_type,$page){
        $goodsModel = $this->dataModel($this::GOODS_LIST);
        $field = "goods_id,goods_name,total_sales_volume+sales_volume as userNum,punch_days as dayNum";
        $where = array(
            'cid' => $cate_id,
            'punch_days' => ['gt',2],
        );
        $order = ($sort_type == 1) ? "goods_sort desc" : "userNum desc";
        $result = $goodsModel->field($field)->where($where)->order($order)->limit(($page - 1) * 20,20)->select();
        return $result;
    }

    /**
     * 打卡商品数量
     * @return int 数量
     */
    public function goods_count($cate_id = 0){
        $goodsModel = $this->dataModel($this::GOODS_LIST);
        $where = array(
            'cid' => $cate_id,
            'punch_days' => ['gt',2],
        );
        $count = $goodsModel->where($where)->count();
        return $count;
    }

    /**
     * 用户支付信息查询
     * @param $user_id
     * @return array 一维数组
     */
    public function pay_info($user_id){
        $payModel = $this->dataModel($this::CLOCK_PAY_LOG);
        $field = "user_id,goods_id,status,pay_time,day_num,goods_info,refund_time";
        $result = $payModel->field($field)->where(['user_id'=>$user_id])->find();
        return $result;
    }

    public function refund_info($user_id,$order_no){
        $payModel = $this->dataModel($this::CLOCK_PAY_LOG);
        $where = array(
            'user_id' => $user_id,
            'order_no' => $order_no,
            'status' => 1,
        );
        $result = $payModel->where($where)->count();
        return $result;
    }

    /**
     * 用户支付信息更新
     * @param $user_id
     * @param $data
     * @return mixed：受影响的条数，无修改则返回0
     */
    public function pay_info_update($user_id,$data){
        $payModel = $this->dataModel($this::CLOCK_PAY_LOG);
        $where = array(
            'user_id' => $user_id,
        );
        $result = $this->update_data(1,$payModel,$where,$data);
        return $result;
    }

    /**
     * 用户打卡信息查询
     * @param $user_id
     * @return array 二维数组
     */
    public function clock_info($user_id){
        $clockModel = $this->dataModel($this::CLOCK_CLOCK_LOG);
        $result = $clockModel->field("user_id,create_time")->where(['user_id'=>$user_id])->order("create_time desc")->select();
        return $result;
    }

    /**
     * 用户邀请信息查询
     * @param $user_id
     * @return array 一维数组
     */
    public function inviter_info($user_id){
        $inviterModel = $this->dataModel($this::CLOCK_INVITER);
        $field = "user_id,b_user_id,pay_time";
        $where = array(
            'user_id' => $user_id,
            'pay_time' => ['gt',strtotime(date("Y-m-d"), time())],
        );
        $result = $inviterModel->field($field)->where($where)->order("pay_time desc")->find();
        return $result;
    }

    /**
     * 邀请表数据写入
     * @param $data
     * @return mixed：添加数据的主键
     */
    public function inviter_info_insert($data){
        $inviterModel = $this->dataModel($this::CLOCK_INVITER);
        $result = $this->insert_data_one($inviterModel,$data);
        return $result;
    }

    /**
     * 用户领取礼物记录查询
     * @param $user_id
     * @return int|string
     */
    public function user_gift($user_id,$coupon_id){
        $giftModel = $this->dataModel($this::CLOCK_GIFT_GET);
        $result = $giftModel->where(['user_id'=>$user_id,'coupon_id'=>$coupon_id])->count();
        return $result;
    }

    /**
     * 礼物领取表数据写入
     * @param $coupon_id
     * @param $user_id
     * @return mixed：添加数据的主键
     */
    public function get_gift($coupon_id,$user_id){
        $giftModel = $this->dataModel($this::CLOCK_GIFT_GET);
        $data = array(
            'user_id' => $user_id,
            'coupon_id' => $coupon_id,
            'create_time' => time(),
        );
        $result = $this->insert_data_one($giftModel,$data);
        return $result;
    }

    /**
     * 打卡记录表数据写入
     * @param $user_id
     * @return mixed：添加数据的主键
     */
    public function clock_insert($user_id){
        $clockModel = $this->dataModel($this::CLOCK_CLOCK_LOG);
        $data = array(
            'user_id' => $user_id,
            'create_time' => time(),
        );
        $result = $this->insert_data_one($clockModel,$data);
        return $result;
    }

    /**
     * 商品详情查询
     * @param $goods_id
     * @return array 一维数组
     */
    public function goods_info($goods_id){
        $goodsModel = $this->dataModel($this::GOODS_LIST);
        $field = "goods_id,goods_name,punch_days,total_sales_volume+sales_volume as sale_num";
        $result = $goodsModel->field($field)->where(['goods_id'=>$goods_id])->find();
        return $result;
    }

    /**
     * 支付信息数据写入
     * @param $data
     * @return mixed：添加数据的主键
     */
    public function pay_info_insert($data){
        $payModel = $this->dataModel($this::CLOCK_PAY_LOG);
        $result = $this->insert_data_one($payModel,$data);
        return $result;
    }

    /**
     * 数据接收记录表数据写入
     * @param $data
     * @return mixed：添加数据的主键
     */
    public function order_receive_insert($data){
        $orderModel = $this->dataModel($this::ORDER_RECEIVE);
        $result = $this->insert_data_one($orderModel,$data);
        return $result;
    }

    /**
     * 商品表数据更新
     * @param $goods_id
     * @param $data
     * @return mixed：受影响的条数，无修改则返回0
     */
    public function goods_info_update($goods_id,$data){
        $goodsModel = $this->dataModel($this::GOODS_LIST);
        $where = array(
            'goods_id' => $goods_id,
        );
        $result = $this->update_data(1,$goodsModel,$where,$data);
        return $result;
    }

    /**
     * 商品下单验证
     * @param $goods_id
     * @param $cate_id
     * @return int|string
     */
    public function goods_check($goods_id,$cate_id){
        $goodsModel = $this->dataModel($this::GOODS_LIST);
        $where = array(
            'goods_id' => $goods_id,
            'cid' => $cate_id,
        );
        $result = $goodsModel->where($where)->count();
        return $result;
    }
}