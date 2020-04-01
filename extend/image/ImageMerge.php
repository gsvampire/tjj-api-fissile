<?php
namespace image;
class ImageMerge
{
    private $resourcesMain; //主文件资源句柄
    private $resourcesOther; //次文件资源句柄
    private $mainWith;
    private $mainHeight;
    private $otherWith = array();
    private $otherHeight = array();
    private $path; //文件保存路径
    public $distance = array();
    public $showImg;
    /**
     * @return array
     */
    public function getDistance()
    {
        return $this->distance;
    }

    /**
     * @param array $distance
     */
    public function setDistance($distance)
    {
        $this->distance = $distance;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param mixed $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }
    public static  function getInstall($mainUrl, $otherUrl,$path = "", $showImg){
        return new ImageMerge($mainUrl, $otherUrl,$path, $showImg);
    }
    /**
     * @return mixed
     */
    public function getMainWith()
    {
        return $this->mainWith;
    }

    /**
     * @param mixed $mainWith
     */
    public function setMainWith($mainWith)
    {
        $this->mainWith[] = $mainWith;
    }

    /**
     * @return mixed
     */
    public function getMainHeight()
    {
        return $this->mainHeight;
    }

    /**
     * @param mixed $mainHeight
     */
    public function setMainHeight($mainHeight)
    {
        $this->mainHeight = $mainHeight;
    }

    /**
     * @return mixed
     */
    public function getOtherWith()
    {
        return $this->otherWith;
    }

    /**
     * @param mixed $otherWith
     */
    public function setOtherWith($otherWith)
    {
        $this->otherWith[] = $otherWith;
    }

    /**
     * @return mixed
     */
    public function getOtherHeight()
    {
        return $this->otherHeight;
    }

    /**
     * @param mixed $otherHeight
     */
    public function setOtherHeight($otherHeight)
    {
        $this->otherHeight[] = $otherHeight;
    }

    /**
     * ImageMerge constructor.
     * @param $mainUrl
     * @param $otherUrl
     * otherUrl equal array or String path  ; if it equal array ,the structure {"http://aaa.png","http://bbb.png"}
     */
    public function __construct($mainUrl, $otherUrl,$path,  $showImg){
        $this->showImg = $showImg;
        $this->resourcesMain = substr($mainUrl, -3) === "png" ?  @imagecreatefrompng($mainUrl): @imagecreatefromjpeg($mainUrl) ;
        $this->resourcesOther = array_map(function($val){return substr($val, -3) === "png" ?   @imagecreatefrompng($val): @imagecreatefromjpeg($val);},$otherUrl);
        $this->mainWith = imagesx($this->resourcesMain);
        $this->mainHeight = imagesy($this->resourcesMain);
        $this->otherWith = array_map(function($val){return imagesx($val);},$this->resourcesOther);
        $this->otherHeight = array_map(function ($val){return imagesy($val);} , $this->resourcesOther);
        $this->path = $path;
    }

    /**
     * @param $type 专门处理头像图片尺寸不合理的问题
     * @param $size 指定图片大小(正方形)
     * @param $count 指定处理图片的数量
     * @return bool
     */
    public function createImage($type = 0, $size=120 ,$count=6){
        if(empty(array_filter($this->distance))){
            return false;
        }
        $i = 0;

        $img = imagecreatetruecolor($this->mainWith , $this->mainHeight);
        imagecopy($img, $this->resourcesMain, 0, 0, 0, 0, $this->mainWith, $this->mainHeight);
        foreach ($this->distance as $key => $value) {
            if($type === 1 && $key<$count){
                $icon = imagecreatetruecolor($size , $size);
                imagecopyresampled($icon, $this->resourcesOther[$i], 0, 0, 0, 0,$size,$size,$this->otherWith[$i], $this->otherHeight[$i]);
                imagecopy($img, $icon, $value['x'], $value['y'], 0, 0, $size, $size);
            }else{
                imagecopy($img, $this->resourcesOther[$i], $value['x'], $value['y'], 0, 0, $this->otherWith[$i], $this->otherHeight[$i]);
            }
            $i++;
        }
        if($this->showImg === 1) {
            header("Content-type:image/jpeg");
            exit(imagejpeg($img));
        };
        return imagejpeg($img ,$this->path , 70);
    }

    public function destory(){
        imagedestroy($this->resourcesMain);
        array_map(function($val){return imagedestroy($val);}, $this->resourcesOther);
    }

    public function __destruct() {
        $this->destory();
    }

}


##############################使用方法开始###################################
#include  "ImageMerge.class.php";
#$mainUrl = "./img/1.jpg";
#$otherUrl = array("./img/2.jpg","./img/3.jpg","./img/4.jpg");
#$img = ImageMerge::getInstall($mainUrl, $otherUrl, "d:/344.png");
#$dis = array(
#   [    'x' => 0,
#        'y' => 30
#    ],
#    [
#        'x' => 180,
#        'y' => 160
#    ],
#    [
#        'x' => 0,
#        'y' => 600
#    ]
#);
#$img->distance = $dis;
#echo $img->createImage() ? "success" : "error";
##############################使用方法结束###################################
