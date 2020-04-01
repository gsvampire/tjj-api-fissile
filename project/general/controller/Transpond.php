<?php
/**
 * 通用中转接口
 */
namespace app\general\controller;
class Transpond extends Common
{
    /**
     * 中转通用接口，用于解决前端请求api跨域问题
     * @param int $api_url_type 用于选择接口域名，默认为api.taojiji.com
     */
    public function request($api_url_type = 1)
    {
        header('Content-Type:application/json; charset=utf-8');
        $request = $this->request->param();
        $this->filter($request);
        if(isset($request['api_url'])){
            $api_url = str_replace('-','/',$request['api_url']);
            switch ($api_url_type){
                case 1:
                    $host = "";
                    break;
                case 2:
                    $host = config('DOMAIN_API_TJJ_SERVICE');
                    break;
                default:
                    $host = "";
                    break;
            }
            $json=(isset($request['.'])&&$request['isImg']==1)?true:false;
            $data = api($api_url,$request,$json,$host);
        }else{
            $data = '';
        }
        if(isset($request['isImg'])&&$request['isImg'] == 1){
            header("Content-type:image/png");
            echo $data;
        }else{
            $this->interlayer($data,1);
        }
    }

    /**
     * java接口中转通用接口，用于解决前端请求javaapi跨域问题
     * @param int $host_type
     */
    public function request_java($host_type = 1){
        header('Content-Type:application/json; charset=utf-8');
        $request = $this->request->param();
        $this->filter($request);
        if(isset($request['api_url'])){
            $host = config("DOMAIN_JAVAAPI_TJJ")[$host_type];
            $api_url = str_replace('-','/',$request['api_url']);
            $data = java_api($api_url,$request,false,$host);
        }else{
            $data = '';
        }
        $this->interlayer($data,1);
    }
}