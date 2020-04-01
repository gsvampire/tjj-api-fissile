<?php
/**
 * Created by PhpStorm.
 * User: guoshuai
 * Date: 2019-07-25
 * Time: 11:05
 */
namespace app\zz\service;


use think\Controller;
use think\Log;

class GrpcService extends Controller{

    const GO_ACCESS_TOKEN='go.micro.srv.wxtoken';

    const APP_ID='wx356365f6db62e947';

    const NEW_APP_ID='wx5f6d55a046565540';

    const SECRET='b775241a19ba88b5f7d65d7b3002bfa2';

    const OSS_UPLOADER = 'go.micro.srv.uploader';

    const OSS_BUCKET = 'image-warehouse';

    const OSS_PATH_AVATAR = '/five/hb/';

    const SSO_SERVICE = 'go.micro.srv.sso';

    const DEFENDER_SERVICE='go.micro.srv.defender';

    const BATCH_USERINFO = 'go.micro.srv.uc';

    /**
     * @param array $arr
     * @return bool
     * 验证用户身份信息
     */
    public static function goCheckUserToken($arr=array())
    {
      try{
          $factoty=new GrpcFactory();
          /** @var \Taojiji\Grpc\Services\SSO\Client\SsoClient $service */
          $service=$factoty->getService(self::SSO_SERVICE);
          /** @var \Taojiji\Grpc\Services\SSO\VerifyTokenResponse $res */
          $res=$service->VerifyToken(intval($arr['user_id']),$arr['token'],$arr['uuid'],(int)$arr['app_resource']);
          Log::info('grpc:'.self::SSO_SERVICE.'在时间: '.date('Y-m-d H:i:s').'验证用户token信息,请求参数为：'.json_encode($arr).
              '返回code信息为:'.$res->getCode().'message为：'.$res->getMessage());
          if($res->getCode()>0) return false;
          return true;
      }catch (\Exception $exception){
          return false;
      }
    }

    /**
     * @throws \Exception
     * 获取access_token
     */
    public static function getGoAccessToken()
    {

       try{
           $factory=new GrpcFactory();
           /**@var \Taojiji\Grpc\Services\WXToken\Client\WXTokenClient $service */
           $service=$factory->getService(self::GO_ACCESS_TOKEN);
           /**@var \Taojiji\Grpc\Services\WXToken\GetTokenResponse $res */
           $res=$service->GetToken((string)self::APP_ID);
           Log::info('grpc:'.self::GO_ACCESS_TOKEN.'在时间：'.date('Y-m-d H:i:s').'获取access_toen,返回code为：'.
               $res->getCode().'token值为: '.$res->getAccessToken().'message为：'.$res->getMessage());
           if($res->getCode()>0){
               return false;
           }
           return $res->getAccessToken();
       }catch (\Exception $exception){
          return false;
       }
    }

    /**
     * @param $path
     * @param $data
     * @param $ext
     * @param $mimeType
     * @param bool $isPrivate
     * @return bool|string
     * 上传图片到阿里云
     */
    public static function UploadImage($path, $data, $ext, $mimeType, $isPrivate = true)
    {
        if(!$data ){
            return false;
        }
        if(!strpos('.', $ext)){
            $ext = '.'.$ext;
        }

        // 获取service
        /** @var \Taojiji\Grpc\Services\Uploader\Client\UploaderClient $service */
        try {
            $factory = new GrpcFactory();
            $service = $factory->getService(self::OSS_UPLOADER);

            // 调用微服务方法
            /** @var \Taojiji\Grpc\Services\Uploader\UploadImageResponse $reply */
            $reply = $service->UploadImage($data, $isPrivate, $ext, $mimeType, self::OSS_BUCKET, $path);
            Log::info('grpc:'.self::OSS_UPLOADER.',code:'.$reply->getCode().',message:'.$reply->getMessage());
            if($reply->getCode() > 0){
                Log::info($reply->getMessage());
                return false;
            }
        }catch (\Exception $e){
            Log::info('调用uploader微服务错误：'.$e->getMessage());
            return false;
        }
        $url = $reply->getImage()->getUrl();
        if(!$url){
            Log::info('调用uploader微服务错误：获取图片url失败！');
            return false;
        }
        return $url;
    }



    public static function userRiskInfo($userId)
    {
        try{
            $factory=new GrpcFactory();
            /** @var \Taojiji\Grpc\Services\Defender\Client\DefenderClient $service */
            $service=$factory->getService(self::DEFENDER_SERVICE);
            /** @var \Taojiji\Grpc\Services\Defender\GetUserRiskInfoResponse $res */
            $res=$service->GetUserRiskInfo(intval($userId));
            Log::info('grpc:'.self::DEFENDER_SERVICE.'在时间:'.date('Y-m-d H:i:s').'获取用户id:'.$userId.'的天域值,code信息为:'.$res->getCode().
                ',message:'.$res->getMessage().',天域值为:'.$res->getData()->getRiskScore());
            if($res->getCode()>0) return false;
            return $res->getData()->getRiskScore();
        }catch (\Exception $exception){
            return false;
        }
    }




    /**
     * @throws \Exception
     * 获取access_token
     */
    public static function getnewGojsticket()
    {

        try{
            $factory=new GrpcFactory();
            /**@var \Taojiji\Grpc\Services\WXToken\Client\WXTokenClient $service */
            $service=$factory->getService(self::GO_ACCESS_TOKEN);
            /**@var \Taojiji\Grpc\Services\WXToken\GetTokenResponse $res */
            $res=$service->GetToken((string)self::NEW_APP_ID);
            Log::info('grpc:'.self::GO_ACCESS_TOKEN.'在时间：'.date('Y-m-d H:i:s').'获取js_ticket,返回code为：'.
                $res->getCode().',ticket: '.$res->getJsapiTicket().',message为：'.$res->getMessage());
            if($res->getCode()>0){
                return false;
            }
            return $res->getJsapiTicket();
        }catch (\Exception $exception){
            return false;
        }
    }


    /**
     * @param $userIds array
     * @return array
     * 批量获取用户信息
     */
    public static function getbatchUserInfo($userIds=array())
    {
        try {
            $factory = new GrpcFactory();
            /** @var  \Taojiji\Grpc\Services\UC\Client\UCClient $service */
            $service = $factory->getService(self::BATCH_USERINFO);
            /** @var \Taojiji\Grpc\Services\UC\GetUserGeneralsResponse $res */
            $res = $service->GetUserGenerals((array)$userIds);
            Log::info('grpc:' . self::BATCH_USERINFO . ',userids:' . json_encode($userIds) . ',code:' . $res->getCode() . ',message:' . $res->getMessage());
            if ($res->getCode() > 0) return [];
            $infos = $res->getUsers();
            $data = [];
            foreach ($infos as $k => $v) {
                $data[$k]['userId'] = $v->getUserId();
                $data[$k]['userName'] = $v->getUserName();
                $data[$k]['nickName'] = $v->getNickname();
                $data[$k]['avatar'] = $v->getAvatar();
            }
            return $data;
        } catch (\Exception $exception) {
            return [];
        }
    }

    /**
     * @param $userId
     * @return array|bool
     * 获取单个用户身份信息
     */
    public static function singleUserInfo($userId)
    {
        try{
            $factory=new GrpcFactory();
            /** @var  \Taojiji\Grpc\Services\UC\Client\UCClient $service */
            $service=$factory->getService(self::BATCH_USERINFO);
            /** @var \Taojiji\Grpc\Services\UC\GetUserGeneralResponse $res */
            $res=$service->GetUserGeneral(intval($userId));
            Log::info('grpc:'.self::BATCH_USERINFO.',user_id:'.$userId.',code:'.$res->getCode().',message:'.$res->getMessage());
            if($res->getCode()>0) return false;
            $nickName=$res->getUser()->getNickname();
            $userName=$res->getUser()->getUserName();
            $avatar=$res->getUser()->getAvatar();
            return ['userId'=>$userId,'userName'=>$userName,'nickName'=>$nickName,'avatar'=>$avatar];
        }catch (\Exception $exception){
            return false;
        }

    }

    /**
     * @param $userId
     * @return array|bool
     * 获取用户天域值
     * 获取用户是否命中黑名单策略
     *
     */
    public static function getUserRiskAllInfo($userId)
    {
        try{
            $factory=new GrpcFactory();
            /** @var \Taojiji\Grpc\Services\Defender\Client\DefenderClient $service */
            $service=$factory->getService(self::DEFENDER_SERVICE);
            /** @var \Taojiji\Grpc\Services\Defender\GetUserRiskInfoResponse $res */
            $res=$service->GetUserRiskInfo(intval($userId));
            Log::info('grpc:'.self::DEFENDER_SERVICE.'在时间:'.date('Y-m-d H:i:s').'获取用户id:'.$userId.',code信息为:'.$res->getCode().',message:'.$res->getMessage());
            if($res->getCode()>0) return false;
            $tianyu=$res->getData()->getRiskScore();
            $hints=[];
            $hintsInfo=$res->getData()->getHints();
            foreach ($hintsInfo as $k=>$v){
                $hints[]=$v;
            }
            return ['tianyu'=>$tianyu,'hintInfo'=>$hints];
        }catch (\Exception $exception){
            return false;
        }
    }

}