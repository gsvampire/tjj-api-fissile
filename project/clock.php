<?php
// 打卡全额返项目入口文件

// 定义应用目录
define('APP_PATH',__DIR__.'/clock/');
define('WEB_CODE_ROOT', dirname(__DIR__));
/**
 * 缓存目录设置
 * 此目录必须可写
 */
define('RUNTIME_PATH', WEB_CODE_ROOT.'/runtime/clock/');      // 项目临时文件主目录
//网站缓存配置
define('LOG_PATH', RUNTIME_PATH . 'logs/');     // 项目日志目录
define('TEMP_PATH', RUNTIME_PATH . 'temp/');     // 项目缓存目录

define('DATA_PATH', RUNTIME_PATH . 'data/');     // 项目数据目录
define('CACHE_PATH', RUNTIME_PATH . 'cache/');     // 项目模板缓存目录
define('UPLOAD_PATH', WEB_CODE_ROOT.'/upload_dir/');	//上传目录
define('VENDOR_PATH',  WEB_CODE_ROOT . '/vendor/');	//第三方类库目录
define('EXTEND_PATH',  WEB_CODE_ROOT . '/extend/');	//自建类库目录
// 加载框架引导文件
require WEB_CODE_ROOT.'/thinkphp/start.php';
