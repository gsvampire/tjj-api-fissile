<?php
/**
 * 缓存配置
 */
return array(
    'DATA_CACHE_TIME' => 0, //长连接时间,REDIS_PERSISTENT为1时有效
    'DATA_CACHE_PREFIX' => '', //缓存前缀
    'DATA_CACHE_TYPE' => 'Redis', //数据缓存类型
    'DATA_EXPIRE' => 0, //数据缓存有效期(单位:秒) 0表示永久缓存
    'DATA_PERSISTENT' => 1, //是否长连接
    'DATA_REDIS_HOST' => '192.168.30.26', //分布式Redis,默认第一个为主服务器
    'DATA_REDIS_PORT' => '6379', //端口,如果相同只填一个,用英文逗号分隔
    'redis'=>[
        'host'       => '127.0.0.1',
        'port'       => 6379,
    ],
    'blackRedis'=>[
        'host'       => '192.168.30.26',
        'port'       => 6379,
    ],
    'zzRedis'=>[
        'host'       => '127.0.0.1',
        'port'       => 6379,
        'password' => '',
        'type'=>'redis',
        'select'=>0,
    ],
);