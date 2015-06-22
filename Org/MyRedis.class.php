<?php

/**
 * Class MyRedis
 * @version 1.0.0
 */

class MyRedis
{
    private static $instance = null;
    static function getInstance($config)
    {
        if( self::$instance === null )
        {
            self::$instance = new MyRedis( $config );
        }
        return self::$instance;
    }

	private $redis = null;
	public $prefix = null;
	
	/**
	 * 	param $conf   array
	 * $conf['prefix']  键名前缀   必须传入
	 * $conf['host']   主机地址   默认：127.0.0.1
	 * $conf['port']   端口          默认：6379
	 * $conf['timeout'] 超时      默认：1
	 * $conf['pwd']   密码         默认：null ，传入密码就自动进行密码验证，不传入就不验证
	 */
	private function __construct( $conf = array() )
	{
		if(!isset($conf['prefix']))
            die("redis未设置键名前缀，请配置：prefix");

		$host = $conf['host'] ? $conf['host'] : '127.0.0.1' ;
		$port = $conf['port'] ? $conf['port'] : 6379;
		$timeout = $conf['timeout'] ? $conf['timeout'] : 1;
		
		$this->redis = new Redis();
		$this->redis->connect($host,$port, $timeout );
		$this->prefix = empty($conf['prefix'] ) ? '' : $conf['prefix'] ;
		if($conf['pwd'])
			$this->redis->auth($conf['pwd']);
		
	}

	function getRedis()
	{
		return $this->redis;
	} 
	
	
	/*
	 * 
	 * 系统方法
	 */
	 
	function allKeys($key = '*')
	{
		return $this->redis->keys($key);
	}
	
	function keys($key = '*')
	{
		return $this->redis->keys($this->prefix.$key);
	}
	
	function type($key)
	{
		if(strpos($key,$this->prefix) === 0)
			return $this->redis->type($key);
		return $this->redis->type($this->prefix.$key);
	}
	

	
	function dbSize()
	{
		return $this->redis->dbSize();
	}
	
	 
	 
	 //公共方法    可删除string  list  set  zset  hash
	function del($key)
	{
		if(is_string($key) || is_array($key))
			return 	$this->redis->delete($this->prefix.$key);
		return false;
	}
	
	
	
	/*
	 * string 操作
	 * 获取：get
	 * 设置：set
	 * 删除：del
	 * 是否存在：exists
	 * 自增：inc
	 * 自减：dec
	 * 
	 * 
	 */
	function get($key)
	{
        $val = $this->redis->get( $this->prefix.$key );
		return self::jsonDecode($val);
	}
	
	function set($key, $val ,$expire = 0)
	{
        $value = self::jsonEncode($val);
		if(intval($expire)>0)
			return $this->redis->setex( $this->prefix.$key , intval($expire) ,$value );
		else
			return $this->redis->set($this->prefix.$key,$value);
	}

    function setZero($key)
    {
        return $this->redis->set($this->prefix . $key , 0);
    }
	
	
	function exists($key)
	{
		return $this->redis->exists($this->prefix.$key);
	}

    function inc($key, $value = 1)
    {
        $value = intval($value);
        if ($value === 1)
            return $this->redis->incr($this->prefix . $key);
        return $this->redis->incrBy($this->prefix . $key, $value);

    }

    function dec($key, $value = 1)
    {
        $value = intval($value);
        if ($value === 1)
            return $this->redis->decr($this->prefix . $key);
        return $this->redis->decrBy($this->prefix . $key, $value);

    }

    /**
	 * list操作：
	 * 
	 * 
	 * 
	 */

    function lPush($key, $value)
    {
        $value = self::jsonEncode($value);
        if ($value !== null)
            return $this->redis->lPush($this->prefix . $key, $value);
        return 0;
    }

    function rPush($key, $value)
    {
        $value = self::jsonEncode($value);
        if ($value !== null)
            return $this->redis->rPush($this->prefix . $key, $value);
        return 0;
    }

    function lPop($key )
    {
        $value = $this->redis->lPop($this->prefix . $key );
        return self::jsonDecode($value);
    }
	 
	 function rPop($key )
	 {
         $value = $this->redis->rPop($this->prefix . $key );
         return self::jsonDecode($value);
	 }
	 
	 
	 /*
	  * 获取list
	  * lGet("a")  返回list的所有值，array
	  * lGet("a",1)  返回list的第2个值，string
	  * lGet("a",1,2)  返回list的第2个-第4个值，array
	  */
	 function lGet($key, $start = null, $end = null)
     {
         if ($start === null) //返回所有
         {
             $arr = $this->redis->lRange($this->prefix . $key, 0, -1);
             foreach ($arr as $k => $v)
                 $arr[$k] = self::jsonDecode($v);
             return $arr;
         }
         if ($end === null) //返回指定的
             return self::jsonDecode($this->redis->lGet($this->prefix . $key, $start));

         $arr = $this->redis->lRange($this->prefix . $key, $start, $end);
         foreach ($arr as $k => $v)
             $arr[$k] = self::jsonDecode($v);
         return $arr;
     }
	 
	 function lDel($key,$value,$count = 0)
	 {
	 	return $this->redis->lRem($this->prefix.$key,$value,$count);
	 }
	 
	 function lTrim($key,$start = 0,$end = -1)
	 {
	 	return $this->redis->lTrim($this->prefix.$key,$start,$end);
	 }
	 
	 function lSize($key)
	 {
	 	return $this->redis->lSize($this->prefix.$key);
	 }
	 

	 /*
	  * 
	  * set 操作
	  */
//	function sAdd($key,$value)
//	{
//		if(is_array($value))
//		{
//			$c = 0;
//			foreach($value as $v)
//			{
//				$c += $this->redis->sAdd($this->prefix.$key, self::jsonEncode($v) );
//			}
//			return $c;
//		}
//		return $this->redis->sAdd($this->prefix.$key, self::jsonEncode($v));
//	}
	
//	function sGet($key)
//	{
//		return $this->redis->sMembers($this->prefix.$key);
//	}
//
//	function sDel($key,$value)
//	{
//		if(is_array($value))
//		{
//			$c = 0;
//			foreach($value as $v)
//			{
//				$c += $this->redis->sRem($this->prefix.$key,$v);
//			}
//			return $c;
//		}
//		return $this->redis->sRem($this->prefix.$key,$value);
//	}
//
//	//查看集合是否存在某个value ，boolean
//	function sExists($key,$value)
//	{
//		return $this->redis->sIsMember($this->prefix.$key,$value);
//	}
//
//	//获取集合的大小  int
//	function sSize($key)
//	{
//		return $this->redis->sSize($this->prefix.$key);
//	}
//
//	//随机删除一个值，并返回此值 string
//	function sPop($key)
//	{
//		return $this->redis->sPop($this->prefix.$key);
//	}
//
//
//	//随机取得一个值， string
//	function sRand($key)
//	{
//		return $this->redis->sRandMember($this->prefix.$key);
//	}
//
//	//交集
//	function sInter()
//	{
//		$args = func_get_args();
//		$num = func_num_args();
//		if($num === 0)
//			return array();
//		if($num === 1)
//			return $this->redis->sInter($this->prefix.$args[0]);
//
//		if($num === 2)
//			return $this->redis->sInter($this->prefix.$args[0],$this->prefix.$args[1]);
//
//		if($num === 3)
//			return $this->redis->sInter($this->prefix.$args[0],$this->prefix.$args[1],$this->prefix.$args[2]);
//
//		if($num === 4)
//			return $this->redis->sInter($this->prefix.$args[0],$this->prefix.$args[1],$this->prefix.$args[2],$this->prefix.$args[3]);
//
//		return array();
//	}
//
//	//并集
//	function sUnion($key1,$key2,$key3,$key4,$key5)
//	{
//		return $this->redis->sUnion($this->prefix.$key1,$this->prefix.$key2,$this->prefix.$key3,$this->prefix.$key4,$this->prefix.$key5);
//	}
//
//	//差集
//	function sDiff($key1,$key2,$key3,$key4,$key5)
//	{
//		$args = func_get_args();
//		$num = func_num_args();
//		if($num === 0)
//			return array();
//		if($num === 1)
//			return $this->redis->sDiff($this->prefix.$args[0]);
//
//		if($num === 2)
//			return $this->redis->sDiff($this->prefix.$args[0],$this->prefix.$args[1]);
//
//		if($num === 3)
//			return $this->redis->sDiff($this->prefix.$args[0],$this->prefix.$args[1],$this->prefix.$args[2]);
//
//		if($num === 4)
//			return $this->redis->sDiff($this->prefix.$args[0],$this->prefix.$args[1],$this->prefix.$args[2],$this->prefix.$args[3]);
//
//		return array();
//	}
//
	
	/*
	 * hash类型
	 * 
	 */
	function hSet($key ,$key1 ,$value , $expire = 0 )
	{
		$v = self::jsonEncode($value);
        $re = $this->redis->hSet($this->prefix.$key, $key1, $v);
        if($expire === 0)
		    return $re;
        return $this->redis->setTimeout($this->prefix . $key , intval($expire) );
	}
	
	
	function hGet( $key ,$key1 = null)
	{
		if($key1 === null) //hgetAll
		{
			$data =  $this->redis->hGetAll($this->prefix.$key);
            if($data)
            {
                foreach($data as $k=>$v)
                    $data[$k] = self::jsonDecode($v);
                return $data;
            }
            return false;
		}
		else if(is_array($key1))
		{ 
			$data = $this->redis->hMGet($this->prefix.$key,$key1);
            if($data)
            {
                foreach($data as $k=>$v)
                    $data[$k] = self::jsonDecode( $v );
                return $data;
            }
            return false;
		}
		else
		{ 
			$data =  $this->redis->hGet($this->prefix.$key,$key1);
            if($data)
				return self::jsonDecode($data);
			return false;
		}
	}

    function hGetPrototype( $key ,$key1 = null)
    {
        if($key1 === null) //hgetAll
        {
            return $this->redis->hGetAll($this->prefix.$key);
        }
        else if(is_array($key1))
        {
            return $this->redis->hMGet($this->prefix.$key,$key1);
        }
        else
        {
            return   $this->redis->hGet($this->prefix.$key,$key1);
        }
    }


    function hSize($key)
	{
		return $this->redis->hLen($this->prefix.$key);
	}
	
	function hDel($key,$key1 = null)
	{
		if($key1===null)
			return $this->redis->delete( $this->prefix.$key );


        if(is_array($key1))
        {
            foreach($key1 as $v)
                $this->redis->hDel( $this->prefix.$key , $v);
            return count($key1);
        }
		return $this->redis->hDel($this->prefix.$key,$key1);
	}
	
	function hKeys($key)
	{
		return $this->redis->hKeys($this->prefix.$key);
	}
	
	function hVals($key)
	{
        $val = $this->redis->hVals($this->prefix.$key);
        if(!$val)
            return false;
        foreach($val  as $k=>$v)
           $val[$k] = self::jsonDecode($v);
		return $val;
	}
	
	function hInc($key,$key1,$num = 1)
	{
		return $this->redis->hIncrBy($this->prefix.$key,$key1,$num);
	}
	 
	
	
	/*
	 * zSet  排序的list
	 * 
	 */ 
	 
//	function zGet($key)
//	{
//		return $this->redis->zRange($this->prefix.$key,0,-1,true);
//	}
//
//	function zAdd($key,$value,$score,$size = 0)
//	{
//		if($this->zExists($key,$value))
//			$this->zDel($key,$value);
//
//		$size = intval($size);
//		if($size !== 0 )
//		{
//			$nowsize = $this->redis->zSize($this->prefix.$key);
//			if($nowsize >= $size )
//				$this->redis->zRemRangeByRank($this->prefix.$key ,0 , $nowsize - $size );
//		}
//
//		return $this->redis->zAdd($this->prefix.$key,$score,$value);
//	}
//
//	function zRange($key,$start = 0,$end = -1,$withScore = false)
//	{
//		return $this->redis->zRange($this->prefix.$key,$start,$end,$withScore);
//	}
//
//	function zDel($key,$value)
//	{
//		return $this->redis->zDelete($this->prefix.$key,$value);
//	}
//
//	function zRevRange($key,$start,$end,$withScore=false)
//	{
//		return $this->redis->zRevRange($this->prefix.$key,$start,$end,$withScore);
//	}
//
//	function zScore($key,$value)
//	{
//		return $this->redis->zScore($this->prefix.$key,$value);
//	}
//
//	function zSize($key,$min=null,$max=null)
//	{
//		if($min===null && $max===null)
//			return $this->redis->zSize($this->prefix.$key);
//
//		return $this->redis->zCount($this->prefix.$key,$min,$max);
//	}
//
//	function zExists($key,$value)
//	{
//		return	$this->zScore($this->prefix.$key,$value)!== false?true:false;
//	}
//
//
//	function zRangeByScore($key,$min,$max,$withScore=false)
//	{
//		if($withScore !== false)
//			return $this->redis->zRangeByScore($this->prefix.$key,$min,array('withscores'=>true));
//		return $this->redis->zRangeByScore($this->prefix.$key,$min);
//	}
//
//	function zRevRangeByScore($key,$min,$max,$withScore = false)
//	{
//		if($withScore !== false)
//			return $this->redis->zRevRangeByScore($this->prefix.$key,$min,array('withscores'=>true));
//		return $this->redis->zRevRangeByScore($this->prefix.$key,$min,$max);
//	}
//
//	function zDelByScore($key,$start,$end)
//	{
//		return $this->redis->zRemRangeByScore($this->prefix.$key,$start,$end);
//	}
//
//	function zDelByRank($key,$start,$end)
//	{
//		return $this->redis->zRemRangeByRank($this->prefix.$key,$start,$end);
//	}
//
//	function zRank($key,$value)
//	{
//		return $this->redis->zRank($this->prefix.$key,$value);
//	}
//
//	function zRevRank($key,$value)
//	{
//		return $this->redis->zRevRank($this->prefix.$key,$value);
//	}
//
    public static function jsonDecode($str){
        if(empty($str))
            return false;
        $re = json_decode($str , true);
        if( is_null($re) )
            return $str;
        return $re;
    }

    public static function jsonEncode($str){
        if(is_array($str))
            return json_encode($str);
        return $str;
    }
	
}