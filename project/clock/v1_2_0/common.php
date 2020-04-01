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

    $method = explode('/', $method);
    unset($params['g']);
    if (count($method) == 2) {
        switch ($host){
            case config('DOMAIN_API_TJJ_SERVICE'):
                $data['g'] = 'Api2_5_0';
                break;
            default:
                $data['g'] = 'Api2_14_1';
                break;
        }
        $data['m'] = $method[0];
        $data['a'] = $method[1];
    }else{
        $data['g'] = $method[0];
        $data['m'] = $method[1];
        $data['a'] = $method[2];
    }

    if(isset($params['uuid'])&&isset($params['user_id'])&&isset($params['token'])){
        $data['user_id'] = $params['user_id'];
        $data['uuid'] = $params['uuid'];
        $data['token'] = $params['token'];
    }
    $data['is_wap'] = 1;
    $data['h5_key'] = 'ad6545fdfgsdf21e';
    $data['fromApp'] = 'tjj';
    $data['mkf_security_code'] = config('MKF_SECURITY_CODE_FOR_WAP');
    $data['ip'] = getIp();
    $post = isset($params['is_post']) ? $params['is_post'] : 0;
    $data = $post ? $data : array_merge((array) $data, (array) $params);
    $query = http_build_query($data);
    $host = empty($host) ? config('DOMAIN_API_TJJ') : $host;
    $url = 'http://' . $host . '/api.php?' . $query;
//    echo $url;die;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($params));
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
    if($post){
        addRedis('user/wxlogin/api',$url.http_build_query($params).'--'.$result);
    }

    if (!$json) {
        $result = json_decode($result, TRUE);
    }
    curl_close($ch);
    return $result;
}

/**
 * 获取java接口数据
 * test
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
    $result['curl_errno'] = curl_errno($ch);
    //$result['url'] = $url;
    curl_close($ch);
    return $result;
}

function dms_upload($params = null)
{
    $host = empty($host) ? config("DOMAIN_DMS_UPLOAD") : $host;
    $url = 'http://' . $host . '/safety.php/index/dmsApi';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    $result = curl_exec($ch);
//    $result['curl_errno'] = curl_errno($ch);
    //$result['url'] = $url;
    curl_close($ch);
    return $result;
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

/*
 * 获取协议头 HTTP、HTTPS
 */
function protocol()
{
    $protocol = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS']== 'on') ? 'https://' : 'http://';
    return $protocol;
}



/*
 * 微信支付日志
 */
function getCurl($url, $data, $isReturn = false)
{
    $backUrl = 'http://' . config('DOMAIN_API_TJJ_WXPAYLOG') .  '/log.php/'  . $url;
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $backUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_TIMEOUT, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

    $error = curl_errno($curl);

    $res = curl_exec($curl);


    curl_close($curl);
    if ($isReturn) {
        $res = json_decode($res, true);
        return $res;
    } else {
        return true;
    }
}


//支付
function pay_api($method, $params = null, $json = false) {
    $method = explode('/', $method);
    $data['g'] = 'Pay';
    $data['m'] = $method[0];
    $data['a'] = $method[1];
    $data['is_wap'] = 1;
    $data['fromApp'] = 'tjj';
    //$data['__NO_CACHE__'] = 1;
    $data['mkf_security_code'] = config('MKF_SECURITY_CODE_FOR_WAP');
    if (empty($params)) {
        unset($_REQUEST['__hash__']);
        $params = $_REQUEST;
    }
    $data = array_merge((array) $data, (array) $params);
    $query = http_build_query($data);
    $url = 'http://' . config('DOMAIN_API_TJJ_SERVICE') . '/api.php?' . $query;
//    dump($url);die;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);

    $error = curl_errno($ch);

    $result = curl_exec($ch);

    if (!$json) {
        $result = json_decode($result, TRUE);
    }
    curl_close($ch);
    return $result;
}


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
    curl_setopt($httph, CURLOPT_CONNECTTIMEOUT , 10);
    curl_setopt($httph, CURLOPT_TIMEOUT, 10);
    $rst = curl_exec($httph);
    return $rst;
}

function addRedis($key,$value){
    $redis=new \think\cache\driver\Redis(config('redis'));
    $handler=$redis->handler();
    $handler->expire($key,3600);
    $handler->sadd($key,$value);
}

