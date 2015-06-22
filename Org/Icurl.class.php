<?php
/**
 * Class Icurl
 * version : 2.0.1
 */

class Icurl
{
    const CHARSET_UTF8 = 'UTF-8';

	/**
	 * $curl_info 数据如下
	 *   ["url"] => string(20) "http://www.baidu.com"
	 *	  ["content_type"] => string(23) "text/html;charset=utf-8"
	*	  ["http_code"] => int(200)
	*	  ["header_size"] => int(579)
	*	  ["request_size"] => int(140)
		  ["filetime"] => int(-1)
		  ["ssl_verify_result"] => int(0)
		  ["redirect_count"] => int(0)
		  ["total_time"] => float(0.359)
		  ["namelookup_time"] => float(0)
		  ["connect_time"] => float(0.141)
		  ["pretransfer_time"] => float(0.141)
		  ["size_upload"] => float(0)
		  ["size_download"] => float(10447)
		  ["speed_download"] => float(29100)
		  ["speed_upload"] => float(0)
		  ["download_content_length"] => float(10447)
		  ["upload_content_length"] => float(0)
		  ["starttransfer_time"] => float(0.265)
		  ["redirect_time"] => float(0)
		  ["certinfo"] => array(0) {
		  }
		  ["redirect_url"] => string(0) ""
	 * 
	 */

    //CURL配置项目
    private $url;
    private $pregMax = 10000000;    //preg允许的匹配最大内容，10M左右
    private $timeout = 20;       //curl_time_out
    private $userAgent = 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.48 Safari/537.36';
    private $urlCharset = self::CHARSET_UTF8;
    private $curl;
    private $cookie ;  //array('phpsessionid'=>'hajsdfjksofjo' , ……)
    private $postData ; //array('name'=>'123','password'=>'123123')
    private $referer ;
    private $requestHeader;
    private $multipart_form_data = false;  //当上传了文件的时候为true ，否则为false


    //运行后的结果
    private $resultContent; //curl_exec的结果中的网页内容
    private $resultHeader;  //curl_exec 的结果中的Head头
    private $resultInfo; //curl_exec 的运行信息
    private $lastRequest ;

    private $setContented = false; //con是不是外部设置的，如果是外部设置的那么preg的时候，不需要验证状态码


	function __construct()  //$gzip 表示该网页是否经过压缩,如果为ture则会进行gzip解码
	{
        $this->curl = curl_init();
        //curl默认配置
        curl_setopt($this->curl ,CURLOPT_RETURNTRANSFER , true);
        curl_setopt($this->curl ,CURLOPT_HEADER, true);
	}

    function setUrl($url , $postData = array() , $charset = null , $multipart_form_data = false){
        $this->url = $url;
        $this->setPostData($postData , $charset , $multipart_form_data);
        return $this;
    }

    function getUrl(){
        return $this->url;
    }


    function setPregMax($pregMax){
        if(!empty($pregMax))
            $this->pregMax = $pregMax;
        return $this;
    }

    function getPregMax(){
        return $this->pregMax;
    }

    function setTimeout($timeout){
        if(!empty($timeout))
            $this->timeout = $timeout;
        return $this;
    }

    function getTimeout(){
        return $this->timeout;
    }

    function setCharset($charset){
        if(!empty($charset))
            $this->urlCharset = $charset;
        return $this;
    }

    function getCharset(){
        return $this->urlCharset;
    }

    function setUserAgent($agent){
        if(!empty($agent))
            $this->userAgent = $agent;
        return $this;
    }

    function getUserAgent(){
        return $this->userAgent;
    }

    function setReferer($referer){
        $this->referer = $referer;
        return $this;
    }

    function getReferer(){
        return $this->referer;
    }

    function setRequestHeader($headerArr){
        if(is_array($headerArr) && !empty($headerArr))
            $this->requestHeader = $headerArr;
        else
            $this->requestHeader = null;
        return $this;
    }

    private function setPostData(array $postData ,$charset = null , $multipart_form_data = false){
        $this->setCharset($charset);
        $this->multipart_form_data = $multipart_form_data;
        if(empty($postData)){
            $this->postData = null;
            return $this;
        }

        //TODO 部分网站可能数据不需要转换，转换后反而无法正常post
//        if($this->getCharset() !== self::CHARSET_UTF8 )
//            $postData = self::parseDataCharset($postData , self::CHARSET_UTF8 , $this->getCharset() );

        $this->postData = $postData;
        return $this;
    }

    private static  function parsePostDataToString( $postData ){
        $re = '';
        if(!empty($postData)){
            foreach($postData as $k=>$v)
                $re.= "{$k}={$v}&";
        }
        return trim($re , '&');
    }


    function setCookie($cookie){
        if(empty($cookie))
            $this->cookie = null;
        else
            $this->cookie = $this->parseCookieToArray($cookie);
        return $this;
    }

    function pushCookie($cookie){
        if(!empty($cookie)){
            $cookie = $this->parseCookieToArray($cookie);
            $this->cookie = self::merge($this->cookie , $cookie);
        }
        return $this;
    }

    function getCookie(){
        return $this->cookie;
    }

    function getCookieString(){
        $re = '';
        if($this->cookie){
            foreach($this->cookie as $k=>$v){
                $re .= "{$k}={$v}; ";
            }
        }
        return trim( trim($re) , ';' );
    }

    private function setLastRequest($field , $value ){
        $this->lastRequest[$field] = $value;
    }

    function getLastRequest(){
        return $this->lastRequest;
    }

    private function parseCookieToArray($cookie){
        $re = array();
        if(empty($cookie))
            return $re;

        if(is_string($cookie)) {
            $temp = explode(';', trim($cookie));
            foreach ($temp as $k => $v) {
                list($key, $val) = explode('=', trim($v));
                if ($val)
                    $re[$key] = $val;
            }
        }

        if(is_array($cookie)){
            foreach($cookie as $k=>$v){
                $v = trim( trim($v) , ';' );
                if(is_numeric($k)){
                    list($key , $val) = explode( '=',trim($cookie));
                    $re[$key] = $val;
                }

                else
                    $re[$k] = $v;
            }
        }
        return $re;
    }


    public static function parseDataCharset($data , $inCharset = self::CHARSET_UTF8 , $outCharset = self::CHARSET_UTF8 ){
        if( $inCharset == $outCharset)
            return $data;

        if(!is_array($data)){
            $temp =  iconv($inCharset,$outCharset.'//IGNORE',$data);
            if(strtolower( $inCharset ) == 'big5'){ //BIG5特殊处理
                $temp = self::unescape2utf8($temp);
            }
            return $temp;
        }

        else{
            foreach($data as $k=>$v)
                $data[$k] = self::parseDataCharset($v , $inCharset , $outCharset);
            return $data;
        }
    }


    public function run($autoSetCookie = true){
        $this->clear();

        if(!$this->getUrl())
            return false;

        ini_set('pcre.backtrack_limit', $this->getPregMax() );
        curl_setopt($this->curl , CURLOPT_URL , $this->getUrl() );
        if(strpos($this->getUrl(),'https') !== false)  //https请求
        {
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, FALSE);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        }
        if($this->getReferer()){
            curl_setopt($this->curl , CURLOPT_REFERER , $this->getReferer());
        }
        curl_setopt($this->curl , CURLOPT_TIMEOUT, $this->getTimeout() );
        curl_setopt($this->curl , CURLOPT_USERAGENT,$this->getUserAgent());

        curl_setopt($this->curl,CURLOPT_POST, !empty($this->postData) );
        if(!empty($this->postData)){
            if($this->multipart_form_data)  //带有文件的postData
                curl_setopt($this->curl,CURLOPT_POSTFIELDS,  $this->postData );
            else                            //不含文件的postData
                curl_setopt($this->curl,CURLOPT_POSTFIELDS,  self::parsePostDataToString( $this->postData ));
        }

        if(!empty($this->requestHeader) && is_array($this->requestHeader)){
            $headerArr = array();
            foreach($this->requestHeader as $k=>$v){
                if(!is_numeric($k))
                    $headerArr[] = $k .':' . $v;
                else
                    $headerArr[] = $v;
            }
            curl_setopt($this->curl , CURLOPT_HTTPHEADER  , $headerArr);
        }


        curl_setopt($this->curl, CURLOPT_COOKIE , $this->getCookieString() );

        $result = curl_exec($this->curl) ;
        $this->resultInfo = curl_getinfo($this->curl);
        $this->resultHeader =  substr( $result , 0 , $this->getResultHeaderSize()); //ResultHeader 不需要编码转换
        $this->resultContent =  $this->parseDataCharset( substr($result , $this->getResultHeaderSize() ) , $this->getCharset() ,self::CHARSET_UTF8 ); //ResultContent 需要编码转换

        $this->setLastRequestData();
        $this->setReferer(  $this->getUrl() );
        if($autoSetCookie)
            $this->getAndPushResultCookie();

        return $this;
    }

    private function setLastRequestData(){
        $this->setLastRequest('url',$this->getUrl());
        if(!empty($this->postData)){
            $this->setLastRequest('request_method', !empty($this->postData) ? 'post' : 'get');
            $this->setLastRequest('request_post_data',$this->postData );
        }
        if(!empty($this->requestHeader) ){
            $this->setLastRequest('request_header',$this->requestHeader);
        }
        $this->setLastRequest('preg_max',$this->getPregMax());
        $this->setLastRequest('timeout',$this->getTimeout());
        $this->setLastRequest('user_agent',$this->getUserAgent());
        $this->setLastRequest('charset',$this->getCharset());
        $this->setLastRequest('cookie',$this->getCookieString());
        $this->setLastRequest('referer',$this->getReferer());
    }


    public function getResultHeader(){
        return $this->resultHeader;
    }


    public function getResultContent(){
        return $this->resultContent;
    }

    public function getResultInfo(){
        return $this->resultInfo;
    }

    public function getResultHeaderSize(){
        return $this->resultInfo['header_size'];
    }

    function getResultRedirect(){
        $url = $this->resultInfo['redirect_url'];
        if(empty($url)){
            preg_match('/Location:(.+)/',$this->resultHeader , $preg);
            if(empty($preg[1])){
                echo "找不到redierct url";
                exit;
            }
            return trim($preg[1]);
        }
        return $url;
    }

    function getUploadSize()
    {
        return $this->resultInfo['size_upload'];
    }

    function getDownloadSize()
    {
        return $this->resultInfo['size_download'];
    }

    function getUploadSpeed(){
        return $this->resultInfo['speed_upload'];
    }

    function getDownloadSpeed()
    {
        return $this->resultInfo['speed_download'];
    }

    function getResultStatus(){
        return $this->resultInfo['http_code'];
    }
	
	private function clear()
	{
		$this->resultHeader = $this->resultContent = $this->resultInfo = $this->lastRequest = null;
		$this->setContented = false;
	} 

	function getResultCookie()
	{
        $cookie = '';
		preg_match_all('/Set-Cookie:(.+);/iU',$this->resultHeader , $re );
		if($re[1])
			$cookie = implode( ';',$re[1] ) . '; ';
		else if ( preg_match('/Set-Cookie:(.+);/iU',$this->resultHeader , $re ) )
			$cookie =  $re[1];
        //$cookie .="test=test;aaa=av;aaa";
        return self::parseCookieToArray($cookie);
	}

    function getAndPushResultCookie(){
        $this->pushCookie( $this->getResultCookie() );
        return $this;
    }


    /**
     * 从外部传入一个resultContent进来
     * @param $content
     */
    function setResultContent($content){
        $this->resultContent = $content;
        $this->setContented = true;
    }



	private function checkErr($mat,$split,$err)  //检查错误
    {
        $re["status"]=0;
		if(!is_array($mat) || empty($mat))   
		{
			$re["msg"]='匹配的正则表达式必须为数组!';
			return $re;
		}
		
		foreach($mat as $v)
		{
			if(strpos($v,'|a') === strlen($v)-2)
				$v = substr($v ,  0 ,strpos($v,'|a'));

			if( preg_match($v,"")===false )
			{
				$re["msg"]='匹配的正则表达式语法错误!' . $v;
				return $re;
			}
		}
		
		if($split && preg_match( $split ,"")===false)
		{
			$re["msg"]="分段的正则表达式结构错误!";
			return $re;
		}
		
		if(!$this->setContented && !$this->resultContent) //非外部设置的内容
		{
			$re["msg"]="获取超时！{$this->url} " ;
			return $re;
		}


		if(!$this->setContented && $this->getResultStatus() != 200)
		{
			$re["msg"]="状态码错误！{$this->url}";
			return $re;
		}
		
		if($err)
		{
			$errs = array();
			if(is_string($err))
				$errs[0] = $err;
			else
				$errs = $err;
			foreach($errs as $v)
			{
				if(preg_match($v , '' ) === false)
				{
					$re["msg"]="匹配错误页面的正则表达式不正确! " . $v;
					return $re;
				}
					
				if(preg_match( $v ,$this->resultContent)===1)
				{
					$re["msg"]="错误的页面!";
					return $re;
				}
				
			}
		}

		$re["status"]=1;
		return $re;
    }


    /**
     * 传入正则表达式 截断resultContent
     * @param $reg string 正则表达式
     * @return $this
     */
    function substrContent($reg){
        preg_match($reg ,$this->resultContent , $re );
        if(!empty($re))
            $this->resultContent = end($re);
        return $this;
    }

    /**
     * 匹配内容
     * @param $mat array   如：array('id'=>'lsitid(.+)sss','key'=>'keysdfaslfj(.+)key') ,  返回正则表达式括号内匹配的内容
     * @param string $split 将整个内容分成各个部分来进行匹配多项
     * @param int $count
     * @param null $err 数组或字符串，如果页面内容包含匹配到$err的正则，则表示进入了错误页面
     * @return mixed
     */
	function preg( $mat , $split='', $count = 0, $err=NULL)   //匹配并返回
	{
		$stime = microtime(true);
		$checkRe = $this->checkErr($mat,$split,$err);
		$re = $checkRe;
		if($checkRe["status"]==1)
		{
			if($split)
			{   
				if(is_string($split))
				{
					preg_match_all( $split ,$this->resultContent,$split_con);
					$s_con = $split_con[0];
				}
				else
				{
					$re["status"]=0;
					$re["msg"]="分段的正则表达式必须为字符串!";
					return $re;
				}
			}
			else
				$s_con[0] = $this->resultContent;

			$num = 0;
			foreach($s_con as $key=>$val)
			{
				$num++;
				if($count && $count < $num)
					break;
						
				foreach($mat as $k=>$v)
				{
					$re['data'][$key][$k] = $this->pregMutil($val , $v);
				}
			}

		}
		
		$re['preg_time'] =  round( microtime(true) - $stime , 3)  ;
		return $re;
	}


    /**
     *
     * 特殊的正则表达式匹配：
     * 支持模式： /aaaaaa/sU|a  ，此时会匹配所有的内容 ，返回的内容是一个数组
     * @param $con
     * @param $reg
     * @return array|mixed|null|string
     */
	private function pregMutil($con , $reg)
	{
		if(strpos($reg , '|a') === strlen($reg)-2 )
		{
			$reg = substr($reg , 0 , strpos($reg,'|a'));
			preg_match_all($reg, $con, $pipei);
		}
		else
			preg_match($reg,$con,$pipei);

		array_shift($pipei);
        $re = count($pipei) === 0 ? null : end($pipei);

		if(is_string($re))
			return trim($re);
		if(is_array($re) ) 
			return explode('|' , $re);
		return $re;
	}


    private static function unescape2utf8($str) {
        $str = rawurldecode($str);
        preg_match_all("/(?:%u.{4})|&#x.{4};|&#\d+;|.+/U",$str,$r);
        $ar = $r[0];
        //print_r($ar);
        foreach($ar as $k=>$v) {
            if(substr($v,0,2) == "%u"){
                $ar[$k] = iconv("UCS-2BE","UTF-8",pack("H4",substr($v,-4)));
            }
            elseif(substr($v,0,3) == "&#x"){
                $ar[$k] = iconv("UCS-2BE","UTF-8",pack("H4",substr($v,3,-1)));
            }
            elseif(substr($v,0,2) == "&#") {

                $ar[$k] = iconv("UCS-2BE","UTF-8",pack("n",substr($v,2,-1)));
            }
        }
        return join("",$ar);
    }


    private static function merge($arr1 , $arr2){
        if(empty($arr1))
            return $arr2;
        if(empty($arr2))
            return $arr2;
        return array_merge($arr1 , $arr2);
    }

    function __destruct(){
        $this->curl = null;
    }

}
