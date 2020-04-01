<?php
namespace aliyun;
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019/5/23
 * Time: 17:59
 */
class Log
{
    /*
     * @params type int 上报类型(0:page 1:api 2:http)
     */
    public $type;
    public $topic='';
    public $source='';
    private $endpoint;
    private $accessKeyId;
    private $accessKey;
    private $project;
    private $logstore;


    public static function Install(){
        return new self();
    }

    public function __construct($type)
    {
        $this->type=$type;
    }

    public function setTopic($topic)
    {
        $this->topic=$topic;
    }
    public function setSource($source)
    {
        $this->topic=$source;
    }

    private function setConf()
    {
        $config = config('DMSLOGFISSION');
        $this->endpoint = $config['endpoint'];
        $this->accessKeyId = $config['accessKeyId'];
        $this->accessKey = $config['accessKey'];
        $this->project = $config['project'];
        $this->logstore = $config['logstore'][$this->type];
    }

    /*
     * @params content array(二维数组长度<5) 上报内容
     */
    public function addDms($content)
    {
        vendor('aliyun_log.Log_Autoload');
        $this->setConf();
        $client = new \Aliyun_Log_Client($this->endpoint, $this->accessKeyId, $this->accessKey);
        $logitems = array();
        $count=count($content);
        if($count>5){return false;}
        for($i=0;$i<$count;$i++){
            $logItem = new \Aliyun_Log_Models_LogItem();
            $logItem->setTime(time());
            $logItem->setContents($content[$i]);
            array_push($logitems, $logItem);
        }
        $req = new \Aliyun_Log_Models_PutLogsRequest($this->project, $this->logstore, $this->topic, $this->source, $logitems);
        $res = $client->putLogs($req);

        $res = (array)($res);
        $dms = true;
        foreach ($res as $k => $v) {
            $dms = (isset($v['_info']['http_code']) && $v['_info']['http_code'] != 200) ? $v['_info']['http_code'] : $dms;
        }
        return $dms;
    }


}

/*
 * 阿里云日志上报
 * 示例：
 * public function test()
    {
        $log=new Log(0);
        $content=[
            [
                'a'=>111,
                'b'=>222,
            ],
            [
                'c'=>111,
                'd'=>222,
            ],
            [
                'a'=>111,
                'b'=>222,
            ],
            [
                'c'=>111,
                'd'=>222,
            ],
            [
                'a'=>111,
                'b'=>222,
            ]
        ];
        $res=$log->addDms($content);
        $res?$this->returnSuccess(1):$this->returnError(-1);
    }
 */