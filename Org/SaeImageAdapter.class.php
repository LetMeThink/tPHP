<?php
/**
 * @version 1.0.0
 */

namespace Admin\Org;
class SaeImageAdapter{

    private $img;
    function __construct(){
        $this->img = new \Think\Image\Driver\Imagick();
    }

    public function open($fromFileName ){
        $this->img->open($fromFileName);
        return $this;
    }


    public function thumb($width, $height, $type){
        $this->img->thumb($width, $height, $type);
        return $this;
    }

    public function save($saeFullName , $ext = null){
        $imagick = $this->img->getImagick();
        if(empty($ext))
            $ext = getFileExt($saeFullName);
        $imagick->setImageFormat($ext);
        //JPEG图像设置隔行扫描
        if('jpeg' == $ext || 'jpg' == $ext){
            $imagick->setImageInterlaceScheme(1);
        }
        // 设置图像质量
        $imagick->setImageCompressionQuality(80);
        //去除图像配置信息
        $imagick->stripImage();

        $saveContent = $imagick->getImagesBlob();
        $saeDomain = \Admin\Controller\FileController::getSaeDomain($saeFullName);
        $st = new \SaeStorage();
        return $st->write($saeDomain , $saeFullName , $saveContent);
    }

}
