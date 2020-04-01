<?php
namespace app\treasure\controller;

//夺宝活动列表
use app\treasure\model\WinPrizeActivity;
use app\treasure\service\IndexService;
use app\treasure\service\RedisService;
use think\Log;
use think\Request;

class Index extends Common {

    public $request;
    public function __construct(Request $request = null)
    {

        parent::__construct($request);

        $this->request = $request;

    }

    /**
     * 夺宝列表
     *  1 =>  推荐排序   2=>即将开奖    3 价值最高
     *
     */
    public function main()
    {
        try{
            $type = intval(trim($this->request->param('type',1))) ;
            $page = intval(trim($this->request->param('page',1)));

            $data = [];
            //必要参数验证
            if(!in_array($type,[1,2,3]))
            {
                $data['result'] = -1000;
                $this->interlayer($data);
            }

            $data['data'] = IndexService::getList($page,$type);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
           // var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());

            Log::info("[夺宝活动][index:main]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 中奖弹窗
     */
    public function awardDialog()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $this->checkUserInfo();
            $data = [];

            $data['data'] = IndexService::awardDialog($userId);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
           // var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());
            Log::info("[夺宝活动][index:awardDialog]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 分享后弹窗
     */
    public function shareDialog()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $this->checkUserInfo();
            $data = [];

            $data['data'] = IndexService::shareDialog($userId);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            // var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());
            Log::info("[夺宝活动][index:shareDialog]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 下单后弹窗
     */
    public function orderDialog()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $this->checkUserInfo();
            $data = [];

            $data['data'] = IndexService::orderDialog($userId);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());
            Log::info("[夺宝活动][index:shareDialog]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 我的夺宝券
     */
    public function myTicket()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $this->checkUserInfo();
            $data = [];

            $data['data'] = [
                'ticket' => IndexService::getMyTicket($userId),
                'url'    => IndexService::getUrl()
            ];
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());

            Log::info("[夺宝活动][index:myTicket]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 最新开奖
     */
    public function newestAward()
    {
        try{
            $page   = intval(trim($this->request->param('page',1)));

            $this->checkUserInfo();

            $data['data']   = IndexService::newestAward($page);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());
            Log::info("[夺宝活动]-[index:newestAward]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }

    }
    //夺宝经历
    public function experience()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $page   = intval(trim($this->request->param('page',1)));

            $this->checkUserInfo();

            $data['data']   = IndexService::experience($userId,$page);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());
            Log::info("[夺宝活动]-[index:experience]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 快捷使用夺宝券
     */
    public function useTicket()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $activity_id = intval(trim($this->request->param('activity_id',0)));
            $this->checkUserInfo();
            //必要参数验证
            $data = [];
            if(!$activity_id)
            {
                $data['result'] = -1000;
                $this->interlayer($data);
            }

            $data['data'] = ['ticket' => IndexService::useTicket($userId,$activity_id)];
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());

            Log::info("[夺宝活动][index:myTicket]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    //我的夺宝券 红点逻辑
    public function redDot()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $this->checkUserInfo();
            $data = [];
            $ticket = IndexService::getMyTicket($userId);
            $data['data'] = [
                'red_dot' => $ticket > 0 ? 1 : 0
            ];
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());

            Log::info("[夺宝活动][index:redDot]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }

    /**
     * 夺宝券明细
     */
    public function ticketDetail()
    {
        try{
            $userId = intval(trim($this->request->param('user_id',0)));
            $page   = intval(trim($this->request->param('page',1)));

            $this->checkUserInfo();

            $data['data']   = IndexService::ticketDetail($userId,$page);
            $data['result'] = 1;
            $this->interlayer($data);
        }catch (\Exception $e){
            //var_dump($e->getMessage()."File:".$e->getFile().":line:".$e->getLine());
            Log::info("[夺宝活动]-[index:ticketDetail]-msg:{$e->getMessage()}:Line:{$e->getLine()}:File:{$e->getFile()}");
            $data['result'] = -1001;
            $this->interlayer($data);
        }
    }


    /**
     * 验证用户信息
     */
    private function checkUserInfo()
    {
        $userId = intval(trim($this->request->param('user_id')));
        $uuid = trim($this->request->param('uuid'));
        $token = trim($this->request->param('token'));
        $data = [];
        if (empty($userId) || empty($uuid) || empty($token) || $userId < 0){
            $data['result'] = -1000;
            $this->interlayer($data);
        }

        //验证用户身份
        $userCheckInfo = $this->goCheckToken($userId, $uuid, $token);
        if (empty($userCheckInfo)){
            $data['result'] = -2;
            $this->interlayer($data);
        }
    }



}
