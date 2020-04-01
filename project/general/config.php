<?php
$config = [
    // 应用调试模式
    'app_debug'              => true,
    // 应用Trace
    'app_trace'              => false,
    // 应用模式状态
    'app_status'             => '',
    // 是否支持多模块
    'app_multi_module'       => true,
    // 入口自动绑定模块
    'auto_bind_module'       => false,

    // +----------------------------------------------------------------------
    // | 模块设置
    // +----------------------------------------------------------------------

    //模块列表
    'MODULE_ALLOW_LIST'   => array('general'),
    // 默认模块名
    'default_module'         => 'general',
    // 禁止访问模块
    'deny_module_list'       => ['common'],
    // 默认控制器名
    'default_controller'     => 'Index',
    // 默认操作名
    'default_action'         => 'index',
    // 默认验证器
    'default_validate'       => '',
    // 默认的空控制器名
    'empty_controller'       => 'Error',
    // 操作方法后缀
    'action_suffix'          => '',
    // 自动搜索控制器
    'controller_auto_search' => false,

    // +----------------------------------------------------------------------
    // | 异常及错误设置
    // +----------------------------------------------------------------------

    // 异常页面的模板文件
    'exception_tmpl'         => WEB_CODE_ROOT.'/project/error.php',

    // +----------------------------------------------------------------------
    // | 日志设置
    // +----------------------------------------------------------------------

    'log'                    => [
        // 日志记录方式，内置 file socket 支持扩展
        'type'  => 'File',
        // 日志保存目录
        'path'  => LOG_PATH,
        // 日志记录级别
        'level' => [],
    ],

    'clock_push_time'=>'11:30:00',
];
$status = require (WEB_CODE_ROOT.'/config/activity_status.php');
$domain = require (WEB_CODE_ROOT.'/config/domain.php');
$filter = require (WEB_CODE_ROOT.'/config/filter.php');
$message = require (WEB_CODE_ROOT.'/config/message.php');
$nickname = require (WEB_CODE_ROOT.'/project/nickname.php');
$redis = require (WEB_CODE_ROOT.'/config/redis.php');
$domain_java = require (WEB_CODE_ROOT.'/config/domain_java.php');
$wx_conf = require (WEB_CODE_ROOT.'/config/wx_conf.php');
$aliyun_log = require (WEB_CODE_ROOT.'/config/aliyun_log.php');
return array_merge($config,$domain,$filter,$message,$redis,$status,$domain_java,$wx_conf,$nickname,$aliyun_log);
