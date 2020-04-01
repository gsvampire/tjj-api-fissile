<?php
/**
 * 分享画图
 */

namespace app\v1_2_0\controller;

use shareImage\ImageMerge;
use think\cache\driver\Redis;

class Shareimage extends Common
{
    #########################redis属性设置##########################################
    public $expiration_twoday = 172800;//缓存2天时间
    public $redis; //设置redis对象
    #########################redisKEY###############################################
    const KEY = "CLOCK-";

    public function __construct()
    {
        parent::__construct();
        $this->protocol = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on') ? 'https://' : 'http://';
        $this->redis = new Redis(config('redis'));
        $this->handler = $this->redis->handler();
    }

    /**
     * 推荐分类前三个商品数据
     * @return array|bool
     */
    public function goodsList()
    {
        $res = controller("v1_1_0/Clock")->goods_list(0, 1, 1, 0);
        //取goodsList的前3个goodsPic
        if (!empty($res['data'])) {
            $goodsList = $res['data'];
            $count = count($goodsList);
            if ($count < 3) {
                for ($i = 0; $i < 3; $i++) {
                    $goodsList = array_merge($goodsList, $res['data']);
                    if (count($goodsList) >= 3) {
                        break;
                    }
                }
            }
            $goodsList = array_slice($goodsList, 0, 3);
            return $goodsList;
        } else {
            return false;
        }
    }

    /**
     * 分享画图
     */
    public function shareImage()
    {
        $img_url = $this->protocol . config('DOMAIN_STATIC') . '/clock/v1_1_0/img/default.png';
        $this->returnSuccess(1, $img_url);

        $key = $this::KEY . "shareImage-" . date("Y-m-d", time());
        if (!empty($this->redis->get($key))) {
            $img_url = $this->redis->get($key);
        } else {
            $filePath = WEB_CODE_ROOT . "/upload_dir/bombActivity/clock/";
            $fileName = date("Y-m-d", time()) . "share.jpg";
            $path = $this->protocol . config('DOMAIN_UPLOAD') . '/bombActivity/clock/' . $fileName;
            $mainUrl = $this->protocol . config('DOMAIN_STATIC') . '/clock/v1_1_0/img/bg.png';
            $default = $this->protocol . config('DOMAIN_UPLOAD') . '/bombActivity/clock/default.png';
            //商品
            $goods = $this->goodsList();
            if ($goods == false) {
                $this->returnSuccess(1, $default);
            }
            $pics = array_column($goods, 'img640Url');
            $prices = array_column($goods, 'minGroupPrice');

            $arr = array(
                ['x' => 10, 'y' => 132],
                ['x' => 146, 'y' => 132],
                ['x' => 282, 'y' => 132]
            );
            $arr1 = array(
                ['x' => 46, 'y' => 295],
                ['x' => 178, 'y' => 295],
                ['x' => 314, 'y' => 295]
            );


            if (!is_dir($filePath)) {
                $filePath1 = WEB_CODE_ROOT . "/upload_dir/bombActivity/";
                if (!is_dir($filePath1)) {
                    mkdir($filePath1);
                }
                mkdir($filePath);
            }

            $image = ImageMerge::getInstall($mainUrl, array($pics[0], $pics[1], $pics[2]), array("￥" . $prices[0], "￥" . $prices[1], "￥" . $prices[2]), $filePath . $fileName, 2, 0);

            $image->setDistance($arr);
            $image->setDistanceText($arr1);
            $image->fontColor = array('212', '11', '2');
            //字体文件
            $image->fontPath = $textpath = WEB_CODE_ROOT . "/project/PingFangMedium.ttf";
//        $image->fontPath = $path = $this->protocol . config('DOMAIN_UPLOAD') . '/clock/v1_1_0/img/PingFangMedium.ttf';
            $dir_result = $image->createImage();
            $img_url = !empty($dir_result) ? $path : $default;
            $this->redis->set($key, $img_url, $this->expiration_twoday);
        }
        $this->returnSuccess(1, $img_url);
    }
}