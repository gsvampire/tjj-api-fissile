<?php
/*
 * 支付宝专享
 * shine
 */
namespace app\index\controller;

use think\cache\driver\Redis;
use think\Controller;
use think\Db;

header("content-type:application/json");
header("Cache-Control: no-cache");
class Goods extends Common
{
    const GOODS = 'alipay_goods';
    const EXPIRE = 300;
    const KEY = 'TEST_FISSILE_INDEX_GOODS_GOODSLISTNEW:';
    const COUNT = 30;

    public function __construct()
    {
        parent::__construct();
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }


    public function check401()
    {
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            header('WWW-Authenticate: Basic realm="My Realm"');
            header('HTTP/1.0 401 Unauthorized');
            echo 'official activity test!';
            exit;
        } else {
            if ($_SERVER['PHP_AUTH_USER'] != 'ALIPAYGOODS' || $_SERVER['PHP_AUTH_PW'] != 'ALIPAYGOODSbJHJns4SD1hk1') {
                header('WWW-Authenticate: Basic realm="Auth!"');
                header('HTTP/1.0 401 Unauthorized');
                echo 'verify error';
                exit;
            }
        }
    }


    public function idList()
    {
        $list =  Db($this::GOODS)->field('id,goods_id,sort')->where('state=0')->order('sort desc')->select();
        return $list;
    }

    public function alipayIdList()
    {
        return 'OK';
        $this->check401();
        $list = $this->idList();
        $this->assign('data',$list);
        return view('Index/index');
    }

    //去重&首次校验
    public function findUrl_abandon($goods_id,$id='')
    {
        $oldId=Db($this::GOODS)->where("state=0 and goods_id='$goods_id'")->column('id');
        if(count($oldId)>0 &&  $oldId[0]!=$id){
            $this->returnError(-22);
        }
    }

    public function addData_abandon()
    {
        $goods_id = (int)$_REQUEST['goods_id'];
        $sort = (int)$_REQUEST['sort'];

        $this->findUrl($goods_id);
        //入库
        $data = [
            'goods_id' => $goods_id,
            'sort' => $sort,
        ];
        $add = Db($this::GOODS)->insert($data);
        $add ? $this->returnSuccess(1,'添加成功') : $this->returnError(-28);
    }

    public function editData_abandon()
    {
        $id = (int)$_REQUEST['id'];
        $goods_id = (int)$_REQUEST['goods_id'];
        $sort = (int)$_REQUEST['sort'];

        $type = $_REQUEST['type'];
        if($type){
            $this->findUrl($goods_id,$id);
        }
        //编辑数据
        $where['id'] = $id;
        $data = $goods_id == '' ? ['state' => 1] : ['goods_id' => $goods_id,'sort' => $sort];
        $updata = Db($this::GOODS)->where($where)->update($data);
        $updata===false ? $this->returnError(-30): $this->returnSuccess(1,'OK') ;
    }

    public function total()
    {
        $count = Db($this::GOODS)->where('state=0')->count();
        return $count;
    }

    public function expires()
    {
        $time = time();
        $mode = $time % 3600;
        $expires = $mode >= 3300 ? 3600 - $mode : $this::EXPIRE;
        return $expires;
    }

    public function goodsList_ababdon($page=1)
    {
        $key = $this::KEY.$page;
        $data = $this->redis->get($key);
        if(!$data){
            $count = $this->total();
            $total = ceil($count/$this::COUNT);
            $start = ($page-1)*$this::COUNT;
            try{
                $goods_id = Db($this::GOODS)->field('goods_id')->where('state=0')->order('sort desc')->limit($start,$this::COUNT)->select();
            }catch(Exception $e){
                $message = $e->getMessage();
                $this->apiLog($_REQUEST,$message);
                $this->returnError(-1,$message);

            }
            if($goods_id == []){
                $this->apiLog($goods_id,'IDS NULL');
                $this->returnError(-1,'IDS NULL');
            }
            $goods_id = array_column($goods_id,'goods_id');
            $goods_id = implode($goods_id,',');
            $params = [
                'ids'=>$goods_id,
                'is_post'=>1,
            ];

            $host =config('DOMAIN_JAVAAPI_TJJ')[8];
            $goodsList = java_api('getGoods/info',$params,false ,$host);
            $goodsList = $goodsList['data'];
            $data = [
                'goodsList'=>$goodsList,
                'totalPage'=>$total,
            ];
            if($goodsList){
                $this->redis->set($key,$data,$this->expires());
            }
        }
        $this->returnSuccess(1,$data);
    }


    /**************************************************改版*******************************************************/
    public function idListNew()
    {
        $list =  Db($this::GOODS)->field('goods_id')->where('state=0')->select();
        $goods_id = array_column($list,'goods_id');
        return $goods_id;
    }

    public function idsList()
    {
        $this->check401();
        $list = $this->idListNew();
        $list = implode("\n",$list);
        $this->assign('data',$list);
        return view('Index/multi');
    }

    public function newAdd()
    {
        try{
            $data =$_REQUEST['goods_id'];
            $data = explode("\n",$data);
            //去重 排序
            $data = array_unique($data);
            $data = array_values($data);
            $insertData = [];
            foreach ($data as $k=>$v){
                if(!preg_match('/^\d+$/',$v)){
                    $this->apiLog($data,'参数不合法');
                    $this->returnError(-26);
                }else{
                    $insertData[$k]['goods_id']= $v;
                    $insertData[$k]['sort']= $k;
                }
            }
            Db::startTrans();
            $delete = Db($this::GOODS)->where('state=0')->delete();
            if($delete){
                $res = Db($this::GOODS)->insertAll($insertData);
            }else{
                $this->apiLog($data,'delete失败');
                $this->returnError(-1,'delete失败');
            }

        }catch(Exception $e){
            Db::rollback();
            $message = $e->getMessage();
            $this->apiLog($_REQUEST['goods_id'],$message,$insertData);
            $this->returnError(-1,$message);
        }
        if($res==count($insertData)){
            Db::commit();
            $this->apiLog($_REQUEST['goods_id'],'支付宝数据更新成功:'.$res,$insertData,1);
            $this->returnSuccess(1,'更新成功:'.$res);
        }else{
            Db::rollback();
            $this->apiLog($_REQUEST['goods_id'],'支付宝数据更新失败:'.$res,$insertData,1);
            $this->returnSuccess(-1,'更新失败:'.$res);
        }
    }

    public function goodsList($page=1)
    {
        $key = $this::KEY.$page;
        $data = $this->redis->get($key);

        if(!$data){
            $count = $this->total();
            $total = ceil($count/$this::COUNT);
            $start = ($page-1)*$this::COUNT;

            $data['totalPage'] = $total;

            try{
                $goods_id = Db($this::GOODS)->field('goods_id')->where('state=0')->limit($start,$this::COUNT)->select();
            }catch(Exception $e){
                $message = $e->getMessage();
                $this->apiLog($_REQUEST,$message);
                $this->returnError(-1,$message);
            }

            if($goods_id == []){
                $data['goodsList'] = [];
                $this->apiLog($goods_id,'IDS NULL',[],1);
                $page==1?$this->returnError(-1,'IDS NULL'):$this->returnSuccess(1,$data);
            }
            $goods_id = array_column($goods_id,'goods_id');
            $goods_id = implode($goods_id,',');
            $params = [
                'ids'=>$goods_id,
                'is_post'=>1,
            ];
            $host =config('DOMAIN_JAVAAPI_TJJ')[8];
            $goodsList = java_api('getGoods/info',$params,false ,$host);
            $goodsList = isset($goodsList['data'])?$goodsList['data']:[];
//            $data = [
//                'goodsList'=>$goodsList,
//                'totalPage'=>$total,
//            ];
            //$this->apiLog($params,'支付宝列表数据',$data);
            $data['goodsList'] = $goodsList;
            if($goodsList){
                $this->redis->set($key,$data,$this->expires());
            }
        }
        $this->returnSuccess(1,$data);
    }

    

}