<?php
/**
 * 获取api接口数据
 * example api('users/login');
 * @param $method
 * @param null $params
 * @param bool $json
 * @param null $host
 * @return mixed
 */
function api($method, $params = null, $json = false,$host = null) {
    unset($params['g']);
    $params['ip'] = getIp();
    $post = isset($params['is_post']) ? $params['is_post'] : 0;

    $query = '';
    if($post){
        if(isset($params['uuid'])&&isset($params['user_id'])&&isset($params['token'])){
            $query.= '?user_id='.$params['user_id'].'&token='.$params['token'].'&uuid='.$params['uuid'];
        }
    }else{
        $query = '?'.http_build_query($params);
    }
    $host = empty($host) ? config('DOMAIN_API_TJJ') : $host;
    $url = 'http://' . $host . '/api.php/'.$method . $query;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($params));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);

    if (!$json) {
        $result = json_decode($result, TRUE);
    }

    $curlErr = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $condition = (!isset($result['result']) || $result['result']!=1)?1:0;
    if($curlErr>0 || $condition){
        $content[]=[
            'apiType'=>2,
            'curlErrNo'=>isset(config('CURL')[$curlErr])?config('CURL')[$curlErr]:$curlErr.' not found',
            'curlCode'=>$code,
            'url'=>$_SERVER['REQUEST_URI'],
            'apiUrl'=>$url,
            'params'=>json_encode($params),
            'data'=>json_encode($result),
            'logLevel'=>$curlErr==0?1:3,
            'clientIp'=>$params['ip'],
            'logTime'=>time(),
        ];
        addApiLog($content);
    }

    return $result;
}

/**
 * 获取java接口数据
 * @param $method
 * @param null $params
 * @param bool $json
 * @param null $host
 * @return mixed
 */
function java_api($method, $params = null, $json = false,$host = null){
    $post = isset($params['is_post']) ? $params['is_post'] : 0;
    $data['ip'] = getIp();
    $data = $post == 1 ? $data : array_merge((array) $data, (array) $params);
    $query = http_build_query($data);
    $host = empty($host) ? config("DOMAIN_API_TJJ_JAVA") : $host;
    $url = 'http://' . $host . '/' . $method.'?'. $query;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($params));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    if (!$json) {
        $result = json_decode($result, TRUE);
    }

    $curlErr = curl_errno($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $condition = (!isset($result['result']) || $result['result']!=1)?1:0;
    if($curlErr>0 || $condition){
        $content[]=[
            'apiType'=>1,
            'curlErrNo'=>isset(config('CURL')[$curlErr])?config('CURL')[$curlErr]:$curlErr.' not found',
            'curlCode'=>$code,
            'url'=>$_SERVER['REQUEST_URI'],
            'apiUrl'=>$url,
            'params'=>json_encode($params),
            'data'=>json_encode($result),
            'logLevel'=>$curlErr==0?1:3,
            'clientIp'=>$data['ip'],
            'logTime'=>time(),
        ];
        addApiLog($content);
    }
    return $result;
}

function addApiLog($content)
{
    try{
        $aliyun=new \aliyun\Log(1);
        $aliyun->addDms($content);
    }catch(\think\Exception $e){
        $e->getMessage();
    }
}

/*
 * 获取IP
 */
function getIp() {
    if (!empty($_SERVER["HTTP_CLIENT_IP"])) {
        $ip = $_SERVER["HTTP_CLIENT_IP"];
    } else if (!empty($_SERVER["HTTP_X_REAL_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_REAL_FORWARDED_FOR"];
    } else if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
        $ip = $_SERVER["HTTP_X_FORWARDED_FOR"];
    } else if (!empty($_SERVER["REMOTE_ADDR"])) {
        $ip = $_SERVER["REMOTE_ADDR"];
    } else {
        $ip = '';
    }
    $ip=explode(',',$ip);
    return $ip[0];
}

