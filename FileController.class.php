<?php

/**
 * 上传文件专用类
 * @version 1.0.2
 */
namespace Admin\Controller;
class FileController extends BaseController
{
    private static function getUploadClass(){
        static $class ;
        if($class === null){
            $class = new \Think\Upload();
            $class->rootPath = self::getBasePath();
            $class->autoSub = false;
        }
        return $class;
    }

    private static function getThumbClass(){
        static $class ;
        if($class === null){
            //TODO SAE临时解决方案
            if(IS_SAE)
                $class = new \Admin\Org\SaeImageAdapter();
            else
                $class = new \Think\Image();
        }
        return $class;
    }

    private static function getBasePath(){
        $base_dir = C('BASE_DIR');
        if(!$base_dir){
            $info = "未读取到 BASE_DIR 配置. ";
            LogController::e($info . __FILE__ . __LINE__);
            error($info);
        }
        return self::parsePath( $base_dir );
    }

    /**
     * @param array $file  $_FILES中的单个元素
     * @param string $savepath 存放目录
     * @param string|array $ext 允许的扩展名
     * @param bool $newname 是否重命名
     * @return array
     */
    private function uploadOne($file, $savepath, $ext = '*', $newname = true) //上传一个文件
    {
        $up = self::getUploadClass();
        if($ext === '*' )
            $up->exts = array();
        else if( $ext)
            $up->exts = is_array($ext) ? $ext : array($ext);

        if (empty($newname))
            $up->saveName = '';

        $up->savePath = $savepath;
        $re = $up->uploadOne($file, $savepath);
        if ($re) {
            $ret['status'] = 1;
            $ret['info'] = $re;
        } else {
            $ret['status'] = 0;
            $ret['info'] = $up->getError();
        }
        return $ret;
    }


    /**
     * 上传图片公共方法，根据$_REQUEST['id']判断是否是修改，如果是修改且有上传了新图片，则删除原图片后上传新图片。否则，直接上传新图片
     * @param
     * @param string $field
     * @param int $must 默认是 1，表示添加时必须有此项，其他表示添加时可以没有此项
     * @param $table string 数据表名称
     * @param $confField string  上传配置字段
     * @param $field string 数据表中图片的字段名称
     * @param $must int   字段是否允许空
     * @param $newname int  string 是否重命名上传的文件
     * @param $allowExt  array 允许上传的扩展名
     * @return string
     */
    public function upload($table, $confField, $field, $must = 1, $newname = 1, $allowExt = array("jpg", "png", 'gif', 'jpeg')) //上传图片, 新增图片和修改图片都做了判断····
    {
        $id = is_numeric($_REQUEST['id']) ? $_REQUEST['id'] : 0;
        if (!$table)
            $table = $_REQUEST['table'];
        $confField = strtolower($confField);

        $old_img = '';
        if ($table && $id && $field) //有id
        {
            $t = getDb($table);
            $data = $t->find($id);
            $old_img = $data[$field];
        }

        $re = $this->uploadFile( $_FILES[$field] , $confField , $newname , $allowExt );

        if ($re['status'] == 0) //上传失败
        {
            if ($old_img) //如果是修改的情况，直接直接返回原 图片名称
                return $old_img;
            else if ($must)
                error($re['info']);
            else
                return "";
        } else //上传成功
        {
            $saveName = $re['info']['savename'];
            if ($saveName != $old_img) {
                $this->delAllImage($confField, $old_img); //删除原先图片
            }
            return $saveName;
        }
        return "";
    }

    /**
     * 传入一个 $_FILES中的 object， 上传图片，并生成缩略图
     * @param array $fileObject  如：array('name'=>…… , 'tmp_name'=>…… , 'size'=>…… )
     * @param string $imgConfigField
     * @param bool $newName
     * @param string|array $allowExt
     * @return string|bool
     */
    public function uploadFile($fileObject , $imgConfigField , $newName = true , $allowExt = '*' ){
        $pathConfig = $this->getSavePathConfig($imgConfigField);
        $savePath = $this->getSavePath( $imgConfigField ) ;

        //上传图片
        $re = $this->uploadOne($fileObject, $savePath, $allowExt, $newName);
        //生成缩略图
        if ($re['status'] == 1 && is_array($pathConfig) && !empty($pathConfig['thumb']) ){

            //TODO SAE临时解决方案
            if(IS_SAE){
                $tmpFilePath = $fileObject['tmp_name'];
                $this->createThumb($imgConfigField  , $re['info']['savename'] , $tmpFilePath);
            }
            else
                $this->createThumb($imgConfigField  , $re['info']['savename'] );
            return $re;
        }
        return $re;
    }

    /**
     * @param $imgConfigField
     * @param $fromImageName
     * @param null $imgResource 图片的二进制文件，可为空
     * @return bool|int
     */
    public function createThumb($imgConfigField ,   $fromImageName , $saeImageFullPath = null ){
        $pathConfig = $this->getSavePathConfig($imgConfigField);
        $fullPath  = $this->getFullPath($imgConfigField) ;
        if (is_array($pathConfig) && !empty($pathConfig['thumb']) ) {
            $thumb = $pathConfig['thumb'];
            $this->createThumbDir($imgConfigField , $thumb );

            $fromImageNameNoExt = getFileNameNoExt($fromImageName);
            $fromImageNameExt = getFileExt($fromImageName);
            $thumbClass = self::getThumbClass();

            $thumbNum = 0;
            foreach ($thumb as $v) {
                $thumbDir = $this->getThumbDir($v);
                if ($thumbDir) {
                    $tempExt = $this->getThumbExt($v);
                    $tempExt = $tempExt ? $tempExt : $fromImageNameExt;
                    $thumbFullPath = concatPath( $fullPath , "{$thumbDir}/" . $fromImageNameNoExt . $tempExt);

                    //TODO SAE临时方案
                    if(IS_SAE){
                        $thumbClass->open( $saeImageFullPath )
                            ->thumb($this->getThumbWidth($v) , $this->getThumbHeight($v) ,\Think\Image::IMAGE_THUMB_CENTER)
                            ->save($thumbFullPath);
                    }
                    else
                        $thumbClass->open( concatPath( $fullPath , $fromImageName ) )
                            ->thumb($this->getThumbWidth($v) , $this->getThumbHeight($v) ,\Think\Image::IMAGE_THUMB_CENTER)
                            ->save($thumbFullPath);
                    $thumbNum++;
                }
            }
            return $thumbNum;
        }
        return false;
    }

    private function parseThumbConfig($thumbConf){
        $width = $height = null;
        if($thumbConf['width'])
            $width = $thumbConf['width'];
        if($thumbConf['height'])
            $height = $thumbConf['height'];
        $re = array();
        if(empty($width) || empty($height)){
            LogController::e("缩略图配置错误，未定义width || height");
            error("缩略图配置错误，未定义width || height");
        }
        else {
            $re['thumbDir'] = $width.'_'.$height;
            $re['width'] = $width;
            $re['height'] = $height;
        }

        $re['ext'] = isset($thumbConf['ext']) ? $thumbConf['ext'] : null;
        return $re;
    }

    private function createThumbDir($imgConf , $thumbConf){
        $fullPath = $this->getFullPath($imgConf);
        foreach($thumbConf as $v){
            $thumbDir = $this->getThumbDir($v);
            if($thumbDir && !$this->isDir( concatPath($fullPath , $thumbDir)) )
                $this->mkDir( concatPath($fullPath , $thumbDir));
        }
        return true;
    }

    private function getThumbDir($thumbConf){
        $re = $this->parseThumbConfig($thumbConf);
        return $re['thumbDir'];
    }

    private function getThumbExt($thumbConf)
    {
        return isset($thumbConf['ext']) ? $thumbConf['ext'] : null;
    }

    private function getThumbWidth($thumbConf){
        $re = $this->parseThumbConfig($thumbConf);
        return $re['width'];
    }
    private function getThumbHeight($thumbConf){
        $re = $this->parseThumbConfig($thumbConf);
        return $re['height'];
    }

    public function getSavePathConfig($imgConf){
        $re =  C("img.{$imgConf}");
        if (!$re || (is_array($re) && empty($re['path']))) {
            $info = "未读取到路径配置：img.{$imgConf} . ";
            LogController::e($info . __FILE__ . __LINE__);
            error($info);
        }
        return $re;
    }


    public function getSavePath($imgConf){
        $path = $this->getSavePathConfig($imgConf);
        if(is_string($path))
            return self::parsePath( $path );
        return self::parsePath( $path['path'] );
    }

    public function getFullPath($imgConf){
        $savePath = $this->getSavePath($imgConf);
        $basePath = $this->getBasePath();
        return concatPath($basePath  ,  $savePath);

    }

    private static function parsePath($path){
        return rtrim($path , '/').'/';
    }

    public function getSaveUrl($imgConf){
        $baseUrl = C('BASE_URL');
        if(!$baseUrl){
            $info = "未读取到 BASE_URL 配置. ";
            LogController::e($info . __FILE__ . __LINE__);
            error($info);
        }
        $path = $this->getSavePathConfig($imgConf);
        if(is_string($path))
            return concatPath($baseUrl , $path);
        return concatPath( $baseUrl , $path['path'] );
    }



    /**
     * 公共方法：  删除数据库，且删除对应的图片
     * @param string $table 表名称
     * @param string $field 字段名称
     * @param string $conf 图片配置字段
     * @param int $id id值
     */
    function del($table, $conf, $field = 'img', $id = null)
    {
        if (!is_numeric($id))
            $id = is_numeric($_REQUEST['id']) ? $_REQUEST['id'] : 0;

        if (!$id)
            error('未找到id值');

        $db = getDb($table);
        $wh = "id=$id";
        $role_wh = R("Role/role", array('delete', $table, $id));
        if ($role_wh)
            $wh .= " and $role_wh";

        $data = $db->where($wh)->find();
        if (!$data)
            error("未找到数据");

        $field = explode('&', $field);
        foreach ($field as $v) {
            if ($data[$v]) {
                $this->delAllImage($conf, $data[$v]);
            }
        }
        $db->where("id=$id")->delete() ?  null : error('错误' . $db->getLastSql());
    }


    /**
     * 删除所有图片，包括对应的缩略图，区分sae服务器和普通服务器
     * @param $img_conf string 图片配置字段
     * @param $name string 图片名称
     * @return bool
     */
    private function delAllImage($img_conf, $name)
    {
        if (!$img_conf || !$name)
            return false;

        $imgConf = $this->getSavePathConfig($img_conf);
        $fullPath = $this->getFullPath($img_conf);

        $delRe = $this->deleteFile( concatPath(  $fullPath , $name )); //删除原图
        if (is_string($imgConf))
            return $delRe;
        else {  //删除缩略图
            $thumbs = $imgConf['thumb'];
            $img_name = getFileNameNoExt($name);
            $img_ext = getFileExt($name);
            foreach ($thumbs as $v) {
                $ext = $this->getThumbExt($v);
                $ext = $ext ? $ext : $img_ext;
                $thumb_path = concatPath( $fullPath ,  $this->getThumbDir($v) , $img_name . $ext) ;
                $this->deleteFile($thumb_path);
            }
            return true;
        }
    }

    private function getThumbConfigByDir($configs , $dir){
        foreach ($configs as $v) {  //遍历thumbDir
            $thumbDir = $this->getThumbDir($v);
            if ($thumbDir == $dir)
                return $v;
        }

        //遍历未找到，把dir当作width来处理
        foreach ($configs as $v) {
            $thumbDir = $this->getThumbDir($v);
            if (strpos($thumbDir , $dir) === 0)
                return $v;
        }

        return null;
    }

    /*
     * 获取文件路径 ,可根据传入的$width获取相应缩略图路径
     * $width  intval  或者 array(intval,intval)
     * 前台也会调用 ,前台调用方法 ： R("Admin://File/getFilePath",array('table',80));
     */
    function getFileUrl($imgConf, $dir = null)
    {
        $saveConfig = $this->getSavePathConfig($imgConf);
        $saveUrl = $this->getSaveUrl($imgConf);

        if ($saveConfig) {
            if (is_string($saveConfig) || $dir === null)
                return $saveUrl ;
            else {
                if ( isset($saveConfig['thumb'])) {
                    $thumbConf = $this->getThumbConfigByDir($saveConfig['thumb'] , $dir);
                    if($thumbConf){
                        $thumbExt = $this->getThumbExt($thumbConf);
                        $thumbDir = $this->getThumbDir($thumbConf);
                        if ($thumbExt)
                            return array( concatPath( $saveUrl ,  $thumbDir )  , $thumbExt );
                        else
                            return concatPath( $saveUrl , $thumbDir )  ;
                    }
                }
            }
        }
        return "";

    }

    function show($imgConfName, $name, $dir = null)
    {
        if (empty($imgConfName) || empty($name))
            return '';

        $unThumb = $url = $this->getFileUrl($imgConfName);
        $unThumbUrl = concatPath($unThumb , $name);

        $url = $this->getFileUrl($imgConfName, $dir); //尝试获取相应宽度的缩略图路径
        if (empty($url)) //获取缩略图路径失败，获取通用路径
            $url = $unThumb;

        if (is_string($url))
            $thumbUrl = concatPath( $url , $name );
        else //缩略图改变了原文件的扩展类型，比如从jpg图片变成了png图片{
            $thumbUrl = concatPath( $url[0] , getFileNameNoExt($name) . $url[1]);

        $ext = getFileExt($name);
        if(!in_array($ext,array('png','gif','jpg','jpeg'))) //非图片的情况
            return '<a href="' . U('File/download',array('dir'=>$imgConfName,'name'=>urlencode($name))) . '" target="_blank"><span title="点击下载">' .$name .'</span></a>';

        $style = "max-width:100px;max-height:100px";
        return '<a href="' . $unThumbUrl . '" target="_blank"><img class="lazy" data-original="' . $thumbUrl . '" style="' . $style . '" title="点击查看大图"/></a>';
    }

    //区别sae模式和本地模式
    private function deleteFile($path)
    {
        if(IS_SAE){
            $st = new \SaeStorage();
            $domain = self::getSaeDomain($path);
            $st->delete($domain , $path);
        }
        return unlink($path);
    }

    private function isDir($path)
    {
        return is_dir($path);
    }

    //区别sae模式和本地模式
    private function mkDir($path)
    {
        if(IS_SAE) //sae模式下无法创建文件夹，直接返回true
            return true;
        return mkdir($path , 0777 , true);
    }


    public function download()
    {
        if(!empty($_GET['path']))
        {
            $path = base64_decode($_GET['path']);
            $baseName =  substr($path , strrpos($path , '/') +1 );
        }
        else
        {
            $dir = $_GET['dir'];
            $name = $_GET['name'];
            if(!$dir || !$name)
                error("文件错误");

            $baseName = urldecode($name);
            $name = iconv('utf-8','gb2312',urldecode($name));
            $path = concatPath( C('BASE_DIR') ,  $dir ."/" .$name);
        }
        if(file_exists($path))
        {
            header("Content-Type: application/force-download");
            header("Content-Disposition: attachment; filename=".$baseName);
            readfile($path);
            exit;
        }
        else
            error("文件不存在");

    }

    //请传入绝对地址
    public static function createDownloadUrlByPath($absPath)
    {
        return U('File/download',array('path'=>base64_encode($absPath)));
    }



    /**
     * 全部生成缩略图，该方法仅支持url调用
     *
     */
    public function reCreateThumb(){
        if(empty($_GET['table']) || empty($_GET['field']) )
            die("参数错误!");

        $imgConfField = $_GET['table'];
        $field = $_GET['field'];
        $db = getDb($imgConfField);
        $list = $db->field($field)->select();
        if(!empty($list)){
            $re = array();
            foreach($list as $k=>$v){
                $re[ $v[$field] ] = $this->createThumb( $imgConfField , $v[$field] );
            }
            dump($re);
        }
        else{
            echo "未查询到任何数据:" . $db->getLastSql();
        }
    }

    public static function getSaeDomain(&$saeFullName){
        $saeFullName = ltrim(trim($saeFullName) , './');
        $saeFullName = ltrim($saeFullName , '/');
        list($domain , $saeFullName) = explode('/',$saeFullName , 2);
        return $domain;
    }

}
