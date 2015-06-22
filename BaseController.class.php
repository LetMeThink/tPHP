<?php
/**
 * @version 1.0.1
 */
namespace Admin\Controller;

use Think\Controller;

class BaseController extends Controller
{
    protected $navigation = true;
    protected $nav = ''; //分配到模板页的html
    protected $search = ''; //分配到模板页的html
    protected $main = ''; //分配到模板页的html
    protected $pager = ''; //分配到模板页的html

    private $topNavigation = array();
    private $leftNavigation = array();
    private $leftNavigationSelect = array();

    function __construct()
    {
        parent::__construct();
        header("Content-type:text/html;charset=utf-8");
        debug($_SESSION);
        $this->readConfigFromDb();
    }


    protected  function assignNavigation()
    {
        static $assigned = false;
        if ( !$assigned ) {
            $nav = C('NAVIGATION');
            foreach ($nav as $k => $v) //获取用户权限内所有的导航  左侧导航
            {
                if(!empty($v['func']))
                {
                    $temp = TableController::parseFunc($v['func']);
                    unset($v['func']);
                    $v = merge($temp , $v);
                }

                foreach ($v as $k2 => $v2)
                {
                    if (!isset($v2['power']) || ( hasRole($v2['power'])) )
                    {
                        //未设置验证方法  或者  设置了方法，切方法返回值为true
                        if(!empty($v2['link']) && ( empty($v2['func']) || TableController::parseFunc($v2['func']) ))
                        {
                            $link = $v2['link'];
                            $tpLink = self::parseTPUrl( U($link[0] , $link[1]) );
                            $this->leftNavigation[$k][$k2] = $tpLink;
                        }
                    }
                    //未设置权限，所有人都有权限 ,设置了权限，就验证权限
                }
            }

            foreach ($this->leftNavigation as $k => $v) //获取所有顶部导航
            {
                $link = null;
                if ( !isset( $this->topNavigation[$k] ) || null === $this->topNavigation[$k] ) {  //获取每个topNavigation的默认link
                    $temp =  array_shift($v) ;
                    $link = $temp['url'];
                }
                $this->topNavigation[$k]['link'] = $link;
            }

            $this->findSelectedNavigation(); //标记当前选中的
            $this->filterUnvalidNavigation(); //过滤无效的左边导航

            $this->assign("topNav", $this->topNavigation);
            $this->assign("leftNav", $this->leftNavigationSelect);
            debug($this->topNavigation, "Top navgation");
            debug($this->leftNavigation, "Left navgation");
            debug($this->leftNavigationSelect, "Left navgationSelected");
            $assigned = true;
        }

    }

    private function findSelectedNavigation()
    {
        $currentUrl = self::parseTPUrl();
        foreach ($this->leftNavigation as $k => $v) {
            foreach ($v as $k2 => $v2) {
                $diff = self::compareTPUrl($currentUrl , $v2 , array('p' , 'method','id')) ;
                if( empty($diff) ){
                    $this->leftNavigation[$k][$k2]['selected'] = true;
                    $this->topNavigation[$k]['selected'] = true;
                    return;
                }
            }
        }

    }


    /**
     * 解析TP的url，根据URLmodel进行解析
     * @param $url string  等待解析的URL
     * @return array
     */
    public static function parseTPUrl($url = null){
        if(!$url)
            $url = $_SERVER['REQUEST_URI'];
        static $cache;
        if($cache[$url] === null){
            $re['url'] = $url;
            $url = ltrim( ltrim($url , '/') , 'index.php' );
            debug($url , "去除index.php的URL地址");
            $urlModel = C("URL_MODEL");
            switch($urlModel){
                case 2:
                    $urls = strToArray($url , '/');
                    $re['module'] = array_shift($urls);
                    $re['controller'] = array_shift($urls);
                    $re['action'] = array_shift($urls);
                    foreach($urls as $k=>$v){
                        if($k % 2 == 0)
                            $re['param'][$v] = $urls[$k+1];
                    }
                    break;
                case 0:
                    $url = ltrim($url , '?');
                    $urls = strToArray($url , '&');
                    $temp = array();
                    foreach($urls as $v){
                        list($key , $val) = explode('=' , $v);
                        if($key !== null && $v !== null){
                            $temp[$key] = $val;
                        }
                    }
                    $re['module'] = $temp['m'];
                    unset($temp['m']);
                    $re['controller'] = $temp['c'];
                    unset($temp['c']);
                    $re['action'] = $temp['a'];
                    unset($temp['a']);
                    $re['param'] = $temp;
                    break;
            }
            $cache[$url] = $re;
        }
        return $cache[$url];
    }

    /**
     * 比较两个解析后的url
     * @param $urlOverall array 通过parseTPUrl方法解析后的url，全面的
     * @param $urlSimple array 通过parseTPUrl方法解析后的url，简单的
     * @param $ignoreParam array 忽略的参数
     * @return boolean
     */
    public static function compareTPUrl($urlOverall , $urlSimple , $ignoreParam = null){
        $paramDiff = array_diff($urlOverall['param'], $urlSimple['param']);
        if($ignoreParam)
            foreach($ignoreParam as $ignore)
                unset($paramDiff[$ignore]);
        return $paramDiff;
    }

    protected  function getFristNavigation()
    {
        $this->assignNavigation();
        foreach($this->leftNavigation as $k=>$v)
        {
            $current = current($v);
            return $current['url'];
        }
        LogController::e("未找到导航地址！");
        return null;
    }

    /**
     *过滤不属于当前顶部选中的navgation
     */
    private function filterUnvalidNavigation()
    {
        $find = null;
        foreach ($this->topNavigation as $k => $v) {
            if ($v['selected'] == true) {
                $find = $k;
                break;
            }
        }
        $t = $this->leftNavigation;


        $this->leftNavigationSelect = $t[$find];
//        dump($this->leftNavigation);
//        dump($this->topNavigation);
    }

    /**
     * 从系统配置表读取配置
     */
    private function readConfigFromDb()
    {
        if(!C("CONFIG_FROM_DB"))
            return false;

        static $cache = false;
        if (!$cache) {
            $db = getDb( TABLE_SYSTEM_CONFIG );
            $data = $db->field('k,v')->where("enable = 1")->select();
            debug($data, "从数据库读取的配置文件");
            if($data)
            {
                foreach ($data as $v) {
                    $t = isJson($v['v']) ? json_encode($v['v']) : $v['v'];
                    C($v['k'], $t);
                }
            }
        }
        $cache = true;
    }

    function _out()
    {
        $con = array();
        if ($this->nav)
            $con['nav'] = $this->nav;
        if ($this->search)
            $con['search'] = $this->search;
        if ($this->main)
            $con['main'] = $this->main;
        if ($this->pager)
            $con['pager'] = $this->pager;
        $this->assign('con', $con);
        $this->display("Public:main");
    }

    function success($mes = null, $url = null, $ajax = null , $wait = '')
    {
        parent::success($mes, $url, $ajax , $wait);
    }

    function error($mes = null, $url = null, $ajax = null , $wait = '')
    {
        parent::error($mes, $url, $ajax ,$wait );
    }

    protected function ajaxSuccess($data = null, $info = "操作成功")
    {
        $json['status'] = 1;
        $json['info'] = $info;
        $json['data'] = $data;
        echo json_encode($json);
        exit;
    }

    protected function ajaxFailed($info = "操作失败")
    {
        $json['status'] = 0;
        $json['info'] = $info;
        $json['data'] = null;
        echo json_encode($json);
        exit;
    }

    function __destruct()
    {
        //dump (getRuntime()) ;
    }


}