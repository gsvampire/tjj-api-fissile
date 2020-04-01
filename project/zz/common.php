<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-10
 * Time: 10:17
 */
//curl get请求
function httpGet($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
}


//curl post请求
function httpPost($url, $param = array(),$type = 'application/json')
{
    $httph = curl_init($url);
    switch ($type){
        case 'application/json':
            $data = json_encode($param);
            curl_setopt($httph, CURLOPT_HTTPHEADER, array('Content-Type: '.$type, 'Content-Length: ' . strlen($data)));
            break;
        default:
            $data = http_build_query($param);
    }
    curl_setopt($httph, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($httph, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($httph, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($httph, CURLOPT_POST, 1);
    curl_setopt($httph, CURLOPT_POSTFIELDS, $data);
    curl_setopt($httph, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($httph, CURLOPT_CONNECTTIMEOUT , 5);
    curl_setopt($httph, CURLOPT_TIMEOUT, 5);
    $rst = curl_exec($httph);
    curl_close($httph);
    return $rst;
}


/**
 * @param $arr [要排序的数组]
 * @param $condition [要排序的条件, for  array('id'=>SORT_DESC,'add_time'=>SORT_ASC)]
 * @return bool|mixed
 * 对二维数组多个字段排序
 */
function SortArrByManyField($arr,$condition)
{
    if (empty($condition)) {
        return false;
    }
    $temp = array();
    $i = 0;
    foreach ($condition as $key => $ar) {
        foreach ($arr as $k => $a) {
            $temp[$i][] = $a[$key];
        }
        $i += 2;
        $temp[] = $arr;
    }
    $temp =& $arr;
    call_user_func_array('array_multisort', $temp);
    return array_pop($temp);
}

//返回数据格式-老格式-跟主流程php返回保持一致
function returnOldJson($data = [],  $msg = '',$code = 1){
    $return = [
        'result' => $code,
        'message' => $msg,
    ];
    $return = array_merge($return, $data);
    return json($return);
}

//返回数据格式
function returnJson($data = [],  $msg = '',$code = 1)
{
    $return = [
        'result' => $code,
        'time' => time(),
        'message' => $msg,
        'data'=>$data,
    ];
    return $return;
}

//验证用户身份信息 token java
function checkToken($params=array())
{
  try{
      if(empty($params))  return false;
      $params=http_build_query($params);
      $domanUrl=config('DOMAIN_CHECK_JAVA_TOKEN');
      $curlUrl=$domanUrl.'?'.$params;
      $res=httpGet($curlUrl);
      \think\Log::info('验证用户身份信息,请求参数为:'.$curlUrl.' 返回信息为:'.$res);
      return $res;
  }catch (\Exception $exception){
      return false;
  }
}


function modelsReturnJson($models, $func_name, $params = [])
{
    $models = $models ?: [];
    $data = [];
    foreach ($models as $model) {
        if ($params) {
            $data[] = $model->$func_name($params);
        } else {
            $data[] = $model->$func_name();
        }
    }
    return $data;
}