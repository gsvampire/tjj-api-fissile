<?php

namespace app\zz\controller;

use app\zz\model\ActivityTemplateList;
use app\zz\model\ChoiceShopList;
use think\cache\driver\Redis;
use think\Controller;
use think\Request;

class Activity extends Controller
{
    /**
     * @var
     */
    protected $redis;

    public function __construct(Request $request = null)
    {
        if (strtolower($_SERVER['REQUEST_METHOD']) == 'options') {
            exit;
        }
        parent::__construct($request);
        $this->request = $request;
        $this->redis = new Redis(config('redis'));
    }

    protected function getActivityCacheKey()
    {
        return "activity_201911_index_cache";
    }

    public function index()
    {
        $data = [];
        $cache_key = $this->getActivityCacheKey();
        $cache_data = $this->redis->get($cache_key);
        if ($cache_data === false) {
            $activity_a_template = ActivityTemplateList::findListByTypeA();
            if ($activity_a_template) {
                $data["tabs"] = $activity_a_template->toTypeAJson();
            }
            $choice_shop = ChoiceShopList::findCurrentList();
            if ($choice_shop) {
                $data["choice_shop"] = $choice_shop->toIndexJson();
            }
            $activity_templates = ActivityTemplateList::findCurrentLists();
            if ($activity_templates) {
                $data["activity_templates"] = modelsReturnJson($activity_templates, "toIndexJson");
            }
            $this->redis->set($cache_key, json_encode($data, JSON_UNESCAPED_UNICODE), 600);
        } else {
            $data = json_decode($cache_data, true);
        }
        return returnJson($data, "");
    }

    //清掉缓存
    public function flush_cache()
    {
        $cache_key = $this->getActivityCacheKey();
        $this->redis->rm($cache_key);
    }
}
