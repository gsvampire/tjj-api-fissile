<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-25
 * Time: 10:58
 */

namespace app\zz\service;

use Plu\Grpc\Factory;
use Plu\Grpc\Registries\Consul;
use Plu\Grpc\Registries\Locale;
use Plu\Grpc\Registries\Zookeeper;
use think\Exception;


class GrpcFactory {
    protected $services = [];

    protected $registry;

    /**
     * GrpcFactory constructor.
     *
     * @throws \Exception
     */
    public function __construct() {
        $this->createRegistry();
    }

    /**
     * @throws \Exception
     */
    public function createRegistry() {

        $type = config('consule_type');
        $hosts = config('consule_hosts');
        if(empty($type)||empty($hosts))
            return [];
        if ($type === 'consul') {
            $registry = new Consul($hosts);
        } elseif ($type === 'zookeeper') {
            $registry = new Zookeeper($hosts);
        } elseif ($type === 'locale') {
            $registry = new Locale($hosts);
        }
        if (!isset($registry)) {
            $str = sprintf("Create grpc registry failed, because type: %s", $type);
            throw new \Exception($str);
        }
        $this->registry = $registry;
    }

    /**
     * @param $serviceName
     * @return mixed|\Plu\Grpc\Client
     * @throws \Exception
     */
    public function getService($serviceName) {

        //避免反复初始化
        if (isset($this->services[$serviceName])) {
            return $this->services[$serviceName];
        }

        $client = Factory::getClient($this->registry, $serviceName);
        $this->services[$serviceName] = $client;

        return $client;
    }
}