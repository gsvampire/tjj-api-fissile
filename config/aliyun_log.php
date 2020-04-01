<?php
/**
 * 阿里云日志接入配置
 */
    return array(
        //qa
        'DMSLOGFISSION'=>[
            'endpoint'=>'cn-zhangjiakou.log.aliyuncs.com',   // project 所属区域匹配的 Endpoint
            'accessKeyId'=>'LTAId1HKbwBpP0QK',               // 阿里云访问秘钥 AccessKeyId
            'accessKey' => 'MKUaK0AQyjcaFoo4taF5JWgbRJKvYd', // 阿里云访问秘钥 AccessKeySecret
            'project' => 'tjj-qa-all',                       // 项目名称
//            'logstore' => 'tjj-php-h5-app-log',              // 日志库名称
            'logstore' => [
                'tjj-php-h5-app-log',
                'tjj-php-api-log',
                'tjj-php-h5-http-log',
                'tjj-h5-fissile-performance-log',
            ],              // 日志库名称
        ],
        //生产环境
        'DMSLOG'=>[
            'endpoint'=>'cn-beijing-intranet.log.aliyuncs.com',//'cn-beijing.log.aliyuncs.com',       // project 所属区域匹配的 Endpoint
            'accessKeyId'=>'LTAId1HKbwBpP0QK',               // 阿里云访问秘钥 AccessKeyId
            'accessKey' => 'MKUaK0AQyjcaFoo4taF5JWgbRJKvYd', // 阿里云访问秘钥 AccessKeySecret
            'project' => 'tjj-php-h5-growth-log',            // 项目名称
            'logstore' => [
                'tjj-php-h5-app-log',
                'tjj-php-fissile-api-log',
                'tjj-php-fissile-http-log',//探针
                'tjj-h5-fissile-performance-log',//加载速度
            ]//'tjj-php-h5-app-log',   // 日志库名称
        ],
    );