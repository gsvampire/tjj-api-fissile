<?php

//跨域验证
if (strtoupper($_SERVER['REQUEST_METHOD']) == 'OPTIONS') {
    exit;
}
// 定义应用目录
define('APP_PATH', __DIR__ .'/');
define('WEB_CODE_ROOT', dirname(__DIR__));


/**
 * 缓存目录设置
 * 此目录必须可写
 */

define('RUNTIME_PATH', WEB_CODE_ROOT.'/runtime/');      // runtime

//网站缓存配置
define('LOG_PATH', RUNTIME_PATH . 'logs/');     // 项目日志目录
define('TEMP_PATH', RUNTIME_PATH . 'temp/');     // 项目缓存目录

define('DATA_PATH', RUNTIME_PATH . 'data/');     // 项目数据目录
define('CACHE_PATH', RUNTIME_PATH . 'cache/');     // 项目模板缓存目录
define('UPLOAD_PATH', WEB_CODE_ROOT.'/upload_dir/');	//上传目录
define('VENDOR_PATH',  WEB_CODE_ROOT . '/vendor/');	//第三方类库目录
define('EXTEND_PATH',  WEB_CODE_ROOT . '/extend/');	//自建类库目录

//if($_SERVER['PATH_INFO']=='/anniversary/clear/getOrder'){
//    echo 'OK';
//    exit;
//}

// 加载框架引导文件
require __DIR__.'/../thinkphp/start.php';
