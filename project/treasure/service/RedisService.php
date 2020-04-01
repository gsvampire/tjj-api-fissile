<?php
namespace app\treasure\service;

use think\cache\driver\Redis;

class RedisService
{
    const EXPIRE = 300;

    protected  $redis;
    protected  $config;

    public function __construct()
    {

        $this->config = new Redis(config('redis'));
        $this->redis  = $this->config->handler();
    }

    function get($key)
    {
        $result =  $this->redis->get($key);
        return json_decode($result,true);
    }

    function set($key,$data,$expire = self::EXPIRE)
    {
        return $this->redis->setex($key,($expire + rand(1,10)),json_encode($data));

    }
    function incrBy($key,$step = 1)
    {
        $count = $this->redis->incrby($key,$step);
        $this->redis->Expire($key, 86400 * 7);
        return $count;
    }

    function del($key)
    {
        return $this->redis->del($key);
    }

    /**
     * 原子锁
     * @param $key
     * @param int $time_out
     * @return mixed
     */
    function lock($key,$time_out = 120)
    {
        $result =  $this->redis->setnx($key,1);
        if($result){
            $this->redis->Expire($key, $time_out);
        }
        return $result;
    }


}