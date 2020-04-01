<?php
  //返回message汇总
  //li:单数 xing:双数
   return array(
       'message' => array(
           '1' => '请求成功！',
           '-1' => '请求失败！',
           '-2' => '用户验证未通过',
           '-4' => '订单号为空',
           '-6' => '订单查询失败',
           '-8' => '订单状态修改失败',
           '-10'=> '订单金额必须大于0',
           '-12'=> '用户信息请求失败',
           '-14'=> '未获取到对应用户信息',
           '-16'=> '未获取到对应拼团信息',
           '-18'=> '系统监测到你的环境存在风险，暂时无法参与活动',
           '-20'=> '未获取到地址信息',
           '-3' => '同时进行的免单团订单不能超过五个！',
           '-5' => '已有成团订单，请下载APP！',

           //dms 日志
           '-22'=>'数据已存在，请勿重复添加',
           '-24'=>'数据长度不可超出255字节',
           '-26'=>'数据校验失败',
           '-28'=>'数据添加失败',
           '-30'=>'数据编辑失败',

           /***************808活动******************/
           //签到
           '-32'=>'今天已经签过了~',
           '-34'=>'未获取到后台设置数据',
           '-36'=>'未中奖',
           '-38'=>'已领取',
           '-40'=>'非可领取状态:同一用户',
           '-42'=>'非可领取状态',
           '-44'=>'未到开奖时间',
           '-46'=>'非可领取状态:邀请人不合法',
           '-48'=>'已达领取上限呦~',
           '-50'=>'优惠券溜走啦~',

           //8.8周年庆
           //摇摇乐
           '-31' => '今日已达到领取上限，明天再来吧~',
           '-33' => '您现在没有机会，分享可以获得机会哦~',
           '-37' => '您已经有一次摇奖机会了哦，先摇奖吧~',
           '-39' => '出了点小问题，您的机会没有加上，稍后再试一次吧~',
           '-47' => '网络开了个小差，稍后再试一次吧~',
           '-53' => '抽奖失败~',
           '-57' => '今日分享达到上限，明天再来玩吧~',
           '-59'  => '出了点小问题，稍后再试一次吧~',
           '-63' => '红包溜走啦，请稍后再试',

           //扎气球
           '-41' => '当前不是扎气球活动时间',
           '-43' => '已经参与了当前场次了',

           //分类页
           '-49' => '位置错误，请核对！',
           '-51' => '网络开了个小差，稍后再试吧~',

           '-61' => '没有配置数据~',

           ###########################夺宝活动##################################
           //夺宝活动
           '-1000' => '参数错误',
           '-1001' => '活动过于火爆，请稍后在试~',

           //夺宝券维护
           '-11000' => '您还没有登录哦，请先登录吧~',
           '-11001' => '夺宝券溜走了，稍后再试吧~',
           '-11002' => '网络开了个小差，稍后再来吧~',
           '-11003' => '您今天还没有在夺宝活动中签到，请先去签到~',
           '-11004' => '您今日的夺宝券已达限额，明天再来吧~',
           '-11005' => '夺宝券飞走啦，稍后再来吧~',
           '-11006' => '您的账户出了点小问题，稍后再试一下吧~',
           '-11007' => '啊哦，夺宝券离家出走了，稍后再试下吧~',
           '-11008' => '您选择的活动已经结束了哦，换一个更精彩哦~',
           '-11009' => '活动出了点小问题，稍后再试下吧~',
           '-11010' => '奖品失效啦，换一个更精彩哦~',
           '-11011' => '您没有夺宝券了哦，做任务可以赚夺宝券哦~',
           '-11012' => '您的夺宝券不够了哦，做任务赚取夺宝券吧~',
           '-11013' => '您在本奖品中夺宝券使用达到上限啦，试试别的奖品吧~',
           '-11014' => '活动出了点问题，稍后再试吧~',
           '-11015' => '您的账户没有这么多夺宝券了哦，做任务赚取夺宝券吧~',
           '-11016' => '您的账户余额不足了哦，做任务赚取夺宝券吧~',
           '-11017' => '夺宝券发了个呆，稍后再试下吧~',
           '-11018' => '幸运号码溜走了，稍后再试下吧~',
           '-11019' => '本期活动维护中，稍后再试下吧~',
           '-11020' => '本次使用的夺宝券已超过总数量，重新试一次吧~',
           '-11021' => '您本次使用的夺宝券已超过总数量，重新试一次吧~',
           '-11022' => '活动信息不全，请稍后再试~',
           '-11023' => '您还没有填写地址，请先填写地址吧~',
           '-11024' => '请完整填写地址信息哦~',
           '-11025' => '请填写正确的手机号码哦~',
           '-11026' => '您已经填写过地址了，请不要重复填写哦~',
           '-11027' => '地址录入失败，稍后再试试吧~',
           '-11028' => '活动数据有误，请确认后再操作吧~',
           '-11029' => '网络异常，请稍后再试吧~',
           '-11030' => '您今天已经通过集集美食屋获得过夺宝券了哦，明天再来吧~',
           '-11031' => '活动过于火爆，升级中',
           '-11032' => '用户数据获取失败，请稍后再试吧~',
           '-11033' => '本期还没有开奖，不能填写地址哦~',
           '-11034' => '您不是中奖者，不能填写地址哦~',
           '-11035' => '活动太火爆啦，试试别的吧~',
           '-11036' => '您的操作太过频繁，稍后再来吧~',
      ),
   );
