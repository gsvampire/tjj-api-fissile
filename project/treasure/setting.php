<?php
/**
 * 夺宝活动配置
 */
return array(
    'dailyTicketMax' => 100,//单用户每天最多可获得券数量
    'ticketIndate' => 3,//夺宝券有效期
    'useTicketProportion' => 0.005,//单用户单奖品可用券数比例
    'order_num' => 20,//单个订单得券上限
    'total_num_1' => 50000,//每日报警第一级阈值
    'total_num_2' => 100000,//每日报警第二级阈值
    'total_num_3' => 150000,//每日报警第三级阈值
    'bask_show_day' => 30,//晒单展示时间间隔
);