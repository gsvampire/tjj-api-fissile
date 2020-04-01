<?php
namespace shareImage;
class ImageMerge
{
    private $resourcesMain; //主文件资源句柄
    private $resourcesOther; //次文件资源句柄
    private $mainWith;
    private $mainHeight;
    private $otherWith = array();
    private $otherHeight = array();
    private $path = ""; //文件保存路径
    private $text = array(); //设置文字
    public $distanceText =  array(); //设置文字坐标体系
    public $imgsizeWith = 128; //定义图片尺寸
    public $imgsizeHeight = 128;
    public $distance = array(); //图片坐标体系
    public $showImg = 1;  //1：显示图片url，0：保存图片路径
    public $fontPath; //字体路径
    public $fontSize = "20"; //字体尺寸
    public $fontColor = array("0", "0", "0"); //图片文字颜色设置
    private $status ;  //图片合成样式 1:图片合成图片 2:图片合成图片+文字，3：图片合成文字
    /**
     * @return array
     */
    public function getDistanceText()
    {
        return $this->distanceText;
    }

    /**
     * @param array $distanceText
     */
    public function setDistanceText($distanceText)
    {
        $this->distanceText = $distanceText;
    } //字体颜色
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

    /**
     * @param $mainUrl
     * @param $otherUrl
     * @param array $text
     * @param string $path
     * @param int $showImg
     * @return ImageMerge
     */
    public static  function getInstall($mainUrl, $otherUrl = '',$text = '',$path = '',$status,$showImg){
        return new ImageMerge($mainUrl, $otherUrl ,$text ,$path ,$status,$showImg);
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


    public function __construct($mainUrl, $otherUrl ,$text ,$path ,$status,$showImg){
        $this->showImg = $showImg;
        $this->resourcesMain = substr($mainUrl, -3) === "png" ?  @imagecreatefrompng($mainUrl): @imagecreatefromjpeg($mainUrl) ;
        $this->resourcesOther = array_map(function($val){return substr($val, -3) === "png" ?   @imagecreatefrompng($val): @imagecreatefromjpeg($val);},$otherUrl);
        $this->mainWith = imagesx($this->resourcesMain);
        $this->mainHeight = imagesy($this->resourcesMain);
        $this->otherWith = array_map(function($val){return imagesx($val);},$this->resourcesOther);
        $this->otherHeight = array_map(function ($val){return imagesy($val);} , $this->resourcesOther);
        $this->path = $path;
        $this->text = $text;
        $this->status = $status;
    }

    /**
     * @return bool
     */
    public function createImage(){
        if(empty($this->resourcesMain)){
            return false;
        }
        switch ($this->status){
            case 1:
                if(empty(array_filter($this->distance)) || empty($this->resourcesOther)){
                    return false;
                }
                break;
            case 2:
                if(empty(array_filter($this->distance)) || empty($this->resourcesOther) || empty(array_filter($this->distanceText)) || empty($this->text)){
                    return false;
                }
                break;
            case 3:
                if (empty(array_filter($this->distanceText)) || empty($this->text)){
                    return false;
                }
                break;
            default:
                return false;
        }
        $i = 0;
        $img = imagecreatetruecolor($this->mainWith , $this->mainHeight);
        imagecopy($img, $this->resourcesMain, 0, 0, 0, 0, $this->mainWith, $this->mainHeight);
        foreach ($this->distance as $key => $value) {
            $icon = imagecreatetruecolor($this->imgsizeWith , $this->imgsizeHeight);
            imagecopyresampled($icon, $this->resourcesOther[$i], 0, 0, 0, 0,$this->imgsizeWith,$this->imgsizeHeight,$this->otherWith[$i], $this->otherHeight[$i]);
            imagecopy($img, $icon, $value['x'], $value['y'], 0, 0, $this->imgsizeWith,$this->imgsizeHeight);
            $i++;
        }
        $color = imagecolorallocate($img, $this->fontColor[0], $this->fontColor[1], $this->fontColor[2]);
        foreach ($this->text as $key => $value) {
            imagettftext($img, $this->fontSize, 0, $this->distanceText[$key]['x'], $this->distanceText[$key]['y'], $color, $this->fontPath, $value);
            $i++;
        }

        if($this->showImg === 1) {
            ob_clean();
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
