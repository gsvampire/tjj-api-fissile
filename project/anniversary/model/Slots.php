<?php
/**
 * 摇摇乐
 * User: intel
 * Date: 2019/7/17
 * Time: 21:22
 */
namespace app\anniversary\model;
class Slots extends Common
{
    /**
     * 摇摇乐用户当日参与数据
     * @param $user_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function chance($user_id)
    {
        $slotsModel = $this->dataModel($this::ACTIVITY_SLOTS);
        $field = "user_id,date,draw_num,share_chance";
        $where = array(
            'user_id' => $user_id,
            'date' => date('Y-m-d', time()),
        );
        $result = $slotsModel->field($field)->where($where)->find();
        return $result;
    }

    /**
     * slots表数据写入
     * @param $data
     * @return  mixed：添加数据的主键
     */
    public function insert_chance($data)
    {
        $slotsModel = $this->dataModel($this::ACTIVITY_SLOTS);
        $result = $this->insert_data_one($slotsModel, $data);
        return $result;
    }

    /**
     * slots表数据更新
     * @param $user_id
     * @param $data
     * @return mixed：受影响的条数，无修改则返回0
     */
    public function update_chance($user_id, $data)
    {
        $slotsModel = $this->dataModel($this::ACTIVITY_SLOTS);
        $where = array(
            'user_id' => $user_id,
            'date' => date('Y-m-d', time()),
        );
        $result = $this->update_data(2, $slotsModel, $where, $data);
        return $result;
    }

    /**
     * 摇摇乐用户已领取优惠券
     * @param $user_id
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function got_coupon($user_id)
    {
        $couponModel = $this->dataModel($this::ACTIVITY_SLOTS_COUPON);
        $field = "user_id,coupon_ids";
        $where = array(
            'user_id' => $user_id,
        );
        $result = $couponModel->field($field)->where($where)->find();
        return $result;
    }

    /**
     * slots_coupon表数据写入
     * @param $data
     * @return  mixed：添加数据的主键
     */
    public function insert_coupon($data)
    {
        $couponModel = $this->dataModel($this::ACTIVITY_SLOTS_COUPON);
        $result = $this->insert_data_one($couponModel, $data);
        return $result;
    }

    /**
     * slots_coupon表数据更新
     * @param $user_id
     * @param $data
     * @return mixed：受影响的条数，无修改则返回0
     */
    public function update_coupon($user_id, $data)
    {
        $couponModel = $this->dataModel($this::ACTIVITY_SLOTS_COUPON);
        $where = array(
            'user_id' => $user_id,
        );
        $result = $this->update_data(1, $couponModel, $where, $data);
        return $result;
    }
}