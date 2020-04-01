<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-04
 * Time: 17:35
 */
namespace app\index\controller;
use think\Controller;
use aliyun\Log;

class  Index extends  Controller{

    public function index()
    {
        echo 'Welcome~';
    }

    public function dmsApi($type=1)
    {
        $content = [
            [
                'message'=>'test1s2',
                'code'=>456,
                'logLevel'=>1
            ]
        ];
        $log = new Log($type);

        $dms=$log->addDms($content);
        dump($dms);
    }


}