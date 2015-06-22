<?php
/**
 * 数据表的CURD自动处理类
 * @verision 1.0.2
 *
 */
namespace Admin\Controller;

class TableController extends LoginedController
{
    const MAYBE = 'maybe';
    const ERROR_CONFIG = '操作错误，未找到配置文件 : {?}';
    const ERROR_DATA_UNFIND = '未找到相关数据：{?}';
    const ERROR_POWER = '对不起，您没有足够的权限执行此操作';
    const ERROR_TABLE_NAME = '未定义数据表名称';
    const ERROR_ENGINE = 'Redis引擎配置信息中缺少type：{?}';

    const STRING_SHOW = 'show';
    const STRING_SORT = 'sort';
    const STRING_TABLE = 'table';
    const STRING_FIELD = 'field';
    const STRING_ENGINE = 'source';
    const REDIS_TYPE_SET = 1;
    const REDIS_TYPE_HSET = 2;

    function __construct($tableName = null, $method = null)
    {
        parent::__construct();
        self::getHtmlClass();
        $this->assignNavigation();
        if (!$tableName)
            $tableName = self::getTableName();

        if(!$method)
            $method = empty($_REQUEST['method']) ? self::STRING_SHOW : $_REQUEST['method'];

        if ($tableName)
            $this->_curd($tableName , $method);

    }

    private function _curd($tableName , $method )
    {

//        $this->tableConfig = C($tableName);
//        if (!$this->tableConfig)
//            error(parseArg(self::ERROR_CONFIG, $tableName));

        $methodConfig = self::readConfigByRole($tableName , $method);
        //$methodConfig = C( $tableName . '.' . $method);
        if ($methodConfig && is_string($methodConfig)) //配置内容为字符串，直接作为函数执行
        {
            self::parseFunc($methodConfig);
            success();
        }

        $method = '_' . $method;
        $this->_out($tableName, $method);
    }

    function _out($table = null, $method = null, $navs = null)
    {
        if ($method === '_'.self::STRING_SHOW )
            $con['search'] = self::createSearch($table);
        $con['nav'] = self::createNav($table, $navs);
        $con['table'] = $table;//dump($con);

        $html = self::$method($table);//_show()
        $con['main'] = $html['con'];
        if (isset($html['pager'])) {
            $con['pager'] =  $html['pager'] ;
        }
        if(!empty($html['where']))
            $con['where'] = $html['where'];

        if($method == '_'.self::STRING_SHOW)
            $con['output'] = self::outputData($table , $html['where']);

        $this->assign('title', self::latelyView());
        $this->assign('con', $con);
        $this->display('Public:main');
    }

    public function _output()
    {
        $table = $_GET['table'];
        $where = $_GET['where'];
        $where = empty($where) ? null : base64_decode($where);
        $verify = $_GET['verify'];
        if(empty($table) || empty($verify))
            error("导出失败，传入参数有误！");
        $checkArr = array('table'=>$table , 'where'=> $_GET['where'] , 'uid'=>getAdminId() );
        if($verify != self::getCheckMd5($checkArr) )
            error("导出失败，条件验证错误");
        $db = getDb($table);
        $config = self::readConfigByRole($table ,'output');


        $limit = 9;
        if(isset($config['_limit']))
        {
            $limit = $config['_limit'];
            unset($config['_limit']);
        }

        if(in_array('*',$config))
            $fields = '*';
        else
            foreach($config as $k=>$v)
            {
                if( strpos($k,'_') === 0 )
                    continue;
                if(is_numeric($k))
                    $fields[] = $v;
            }


        if(!empty($limit))
        {
            $count = $db->where($where)->count();
            if($count == 0 )
                error("您请求导出的数据共有{0}条！");
            if($count > $limit)
                error("您请求导出的数据共{$count}条，最多允许导出{$limit}条数据！");
        }

        $data = $db->field($fields)->where($where)->select();
        $keys = array_keys($data[0]);
        $fieldsConfig = array_reverse( self::readConfigField($table) );

        foreach($keys as $v)
            $excelHeaderLine[$v] = empty( $fieldsConfig[$v] ) ? $v : $fieldsConfig[$v];

        import('@.Org.WriteExcel');
        $excel = new \WriteExcel();
        $excel->setMutilArray( array('0'=>$excelHeaderLine) ); //写首行数据
        $excel->setMutilArray($data , 'A' ,  2 );
        $path = concatPath( C('BASE_DIR') , 'output');
        if(!is_dir($path))
            mkdir($path, 0777 , true);
        $excel->saveAndDownload( $path );

    }

    private static function getPrimaryKey($tableName){
        $conf = self::readConfigByRole($tableName , "primary");
        if($conf)
            return $conf;
        return 'id';
    }


    public function _empty()
    {
//		exit('您执行空操作');
    }

    public static function getTableName($must = false)
    {
        $tableName = empty($_REQUEST[self::STRING_TABLE]) ? '' : $_REQUEST[self::STRING_TABLE];
        if($must)
            self::isTable($tableName);
        return $tableName;
    }

    //导出数据
    private static function outputData($table , $where)
    {
        $conf = self::readConfigByRole($table , 'output');
        if(empty($conf))
            return false;
        $base64Where = empty($where) ? "" :  base64_encode($where);
        $checkArr = array('table'=>$table , 'where'=>$base64Where , 'uid'=>getAdminId()  );
        $verify = self::getCheckMd5($checkArr);
        $outputUrl = U('Table/index',array('method'=>'output','table'=>$table,'where'=>$base64Where,'verify'=>$verify));
        return $outputUrl;
    }

    private static function getCheckMd5($arr)
    {
        $arr['private'] = 'zhoutao';
        $checkString = http_build_query( $arr ) ;
        return md5($checkString);
    }

    public static function getAllListener(&$config){
        $arr = array('_prefix','_suffix');
        $re = array();
        foreach($arr as $k=>$v){
            if (!empty($config[$v])) {
                $re[$v] = $config[$v];
                unset($config[$v]);
            }
        }
        return $re;
    }

    public static function onPrefixListener($listener , $data = null){
        $prefixHtml = '';
        if (!empty($listener['_prefix'])) {
            $prefixHtml = self::parseFunc($listener['_prefix'] , $data);
        }
        return $prefixHtml;
    }

    static private function _show($table = null)
    {
        self::isTable($table);
        $autoCheck = C($table.'.check');
        if( !empty($autoCheck) )
            self::autoCheckTable($table);
        $method = self::STRING_SHOW;
        $config = self::readConfigByRole($table, $method);

        if (!$config)
            error(parseArg(self::ERROR_CONFIG, $table . '.' . $method));


        //获取前缀
        $listener = self::getAllListener($config);
        $prefixHtml = self::onPrefixListener($listener);

        $re = self::readData($table, $config);
        $data = $re['data'];
        $return['where'] = $re['where'];
        $return['pager'] = $re['pager'];

        $toggle = $fields = array();
        if ( !isset($_REQUEST['group']) || !$_REQUEST['group'] ) //未分组的情况
        {
            //增加操作项目
            $result = self::parseShow($table, $data, $config);
            debug($result, 'parseShow');
            $data = $result['data'];
            $toggle = $result['toggle'];
            $fields = $result['fields'];
            $attrs = $result['attrs'];
        } else //分组的feild解析
        {
            //解析field
            $data_field = '*';
            if (isset($_REQUEST['field']))
                $data_field = $_REQUEST['field'];
            $field = self::readConfigField($table);
            $data_field = explode(',', $data_field);
            foreach ($data_field as $v) {
                $fields[$field[$v]] = $v;
            }
        }


        $mutil = self::readConfigByRole($table , 'mutil');
        if(!empty($mutil))
            $fields['_mutil']=$mutil;

        $tableHtml = \Html::table($fields, $data, isset($attrs) ? $attrs : null, $toggle);
        if(!empty($mutil))
            //$tableHtml = '<form method="post" action="'.U('Table/index',array('table'=>$table,'method'=>'mutil')).'">' . $tableHtml . '</form>';
            $tableHtml = \Html::node("form", $tableHtml ,
                array('method'=>'post','action'=>U('Table/index',array('table'=>$table,'method'=>'mutil')))
            );

        $suffixHtml = self::onSuffixListener($listener);
        $return['con'] = $prefixHtml . $tableHtml . $suffixHtml;
        return $return;

    }

    public static function onSuffixListener($listener , $data = null){
        $suffixHtml = '';
        if (!empty($listener['_suffix'])) {
            $suffixHtml = self::parseFunc($listener['_suffix'] , $data);
        }
        return $suffixHtml;
    }

    static private function autoCheckTable($table)
    {
        $db = getDb($table);
        //dump($db->query('SHOW COLUMNS FROM '.(getRealTableName(TABLE_SYSTEM_ADMIN."1"))));
        $nowTableConfig = ( $db->query('SHOW COLUMNS FROM '.(getRealTableName($table))));
        $tableField = self::readConfigByRole($table , self::STRING_FIELD);
        $parsedTableConfig = array();
        import('@.Org.AutoTable');
        $autoTable = new \AutoTable( getRealTableName($table) );

        foreach($tableField as $k=>$v)
        {
            if(is_string($v))
                error("{$table} 数据表的{$k}字段必须定义字段类型type");
            foreach($v as $field=>$fieldConf)
            {
                if(empty($v['type']))
                    error("{$table} 数据表的{$k}字段必须定义字段类型type");
                $parsedTableConfig[$k]['Field'] = $k;
                $tempType = $autoTable->createFieldType($v['type'],$v['length']);
                if(empty($tempType))
                    error("系统不支持 {$table} 数据表的 {$k} 字段类型 {$v['type']}");
                $parsedTableConfig[$k]['Type'] = $tempType;
                $parsedTableConfig[$k]['Null'] = !isset($v['null']) ? "NO" : ($v['null'] ? "YES" : "NO");

                $tempKey = isset($v['key']) ? $v['key'] : "";
                if(in_array($tempKey , array('pri','uni','mul')))
                    $tempKey = strtoupper($tempKey);
                else
                    $tempKey = "";
                $parsedTableConfig[$k]['Key'] = $tempKey;

                $parsedTableConfig[$k]['Default'] = isset($v['def']) ? (string)$v['def'] : null;
                $parsedTableConfig[$k]['Extra'] =  isset($v['auto']) && $v['auto'] ? "auto_increment" : "";
            }
        }

        //dump($autoTable->getCreateSqlByConfig($parsedTableConfig));
        $sqls = $autoTable->compareTable($nowTableConfig , $parsedTableConfig);
        if(is_string($sqls))
            $db->query($sqls);
        else if(is_array($sqls))
        {
            foreach($sqls as $v)
                $db->query($v);
        }
    }


    static private function _mutil($tableName)
    {
        $primary = self::getPrimaryKey($tableName);
        if(empty($_POST[$primary]))
            error("你选择的内容为空");
        if(!empty($_POST['del']))
        {
            $db = getDb($tableName);
            $re = $db->where($primary ." in (".implode(',',$_POST[$primary]).")")->delete();
            success("成功删除 {$re} 条数据！");
        }
        error("操作失败！");
    }

    static private function _sort($table = null)
    {
        //提交排序结果
        if(!empty($_POST))
        {
            $table = self::getTableName();
            $config = self::readConfigByRole($table, self::STRING_SORT);
            if(empty($config['_field']))
                error("排序配置文件错误，缺少必须的排序字段");

            $db = getDb($table);
            $primary = self::getPrimaryKey($table);
            $count = count($_POST[$primary]);
            foreach($_POST[$primary] as $v)
            {
                $s[$config['_field']] = $count--;
                $db->where("{$primary}={$v}")->save($s);
            }
            success();
        }

        //显示排序页面
        self::isTable($table);
        $config = self::readConfigByRole($table, self::STRING_SORT);

        if (!$config)
            error(parseArg(self::ERROR_CONFIG, $table . '.' . self::STRING_SORT));

        $config['_pagesize'] = 1000000;
        $re = self::readData($table, $config , self::STRING_SORT );
        $data = $re['data'];
        $return['where'] = $re['where'];
        //$return['pager'] = $re['pager'];

        $toggle = $fields = array();
        //增加操作项目
        $result = self::parseShow($table, $data, $config);
        debug($result, 'parseShow');
        $data = $result['data'];
        $fields = $result['fields'];
        $attrs = $result['attrs'];

        unset($fields['操作']);

        $return['con'] =  \Html::sortTable($fields, $data, isset($attrs) ? $attrs : null );
        return $return;
    }

    static private function _redis($table){
        $key = self::getRedisEngineKey($table);

        $data = array() ;
        switch( self::getRedisEngineType($table) ){
            case self::REDIS_TYPE_SET:
                $data['title'] = "\$Redis->get({$key})";
                $data['content'] = getRedis()->get($key);
                break;
            case self::REDIS_TYPE_HSET:
                $data['title'] = "\$Redis->hGetAll({$key})";
                $temp = getRedis()->hGetPrototype($key);
                foreach($temp as $k=>$v)
                    $data['content'] .= $k . "=>" . $v . "<br />";
                break;
        }

        $return['con'] = <<<EOF
            <h3>{$data['title']}</h3><hr />
            <blockquote><p>{$data['content']}</p></blockquote>
EOF;

        return $return;
    }

    static private function getHtmlClass()
    {
		import('@.Org.Html');
    }

    public static function readConfigField($table)
    {
        return merge(self::defaultFieldName() , C($table . '.field') );
    }

    public static function readConfigByRole($table, $method = self::STRING_SHOW)
    {
        if (isSuperAdmin()) //读取super
        {
            $conf = C($table . ".{$method}=".POWER_SUPER );
            if ($conf)
                return $conf;
        }

        $allConf = C($table);
        unset($allConf["{$method}=super"]); //删除超级管理员的配置文件
        foreach ($allConf as $k => $v) {

            if (strpos($k, $method) === 0) //以method开头的配置
            {
                $role = str_replace($method . '=', '', $k); //读取到相应的role
                if (hasRole($role))
                    return $v;
            }
        }

        if (isset($allConf["{$method}"]))
            return $allConf["{$method}"];

        return null;
    }

    public static function getDbByConfig($table)
    {
        $t = C($table.".table");
        if($t)
            return getDb($t);
        return getDb($table);
    }


    static private function _add($table) //添加或修改
    {
        self::isTable($table);
        $method = 'add';
        $primary = self::getPrimaryKey($table);
        $id = isset($_REQUEST[$primary]) && is_numeric($_REQUEST[$primary]) ? $_REQUEST[$primary] : 0;
        $edit_model = false;
        if ($id) {
            $edit_model = true;
            $method = 'edit';
        }

        $field = self::readConfigField($table);
        $config = self::readConfigByRole($table, 'data');

        if (isset($config['_where'])) {
            $allowids = self::getAllowIds($table, self::parseStringOrFunc($config['_where']));
            if ($edit_model && !in_array($id, $allowids)) {
                error(self::ERROR_POWER);
            }
            unset($config['_where']);
        }

        if (isset($config['_add'])) //添加模式
        {
            if (!$config['_add'] && !$edit_model)
                error(self::ERROR_POWER);
            unset($config['_add']);
        }

        if (isset($config['_edit'])) //修改模式验证权限
        {
            if (!$config['_edit'] && $edit_model)
                error(self::ERROR_POWER);
            unset($config['_edit']);
        }

        $notice = C($table . '.notice');
        $check = self::readConfigByRole($table, 'datasub');

        if (!$config)
            error(parseArg(self::ERROR_CONFIG, $table . '.' . $method));

        $config = self::configFilter($config, $method);
        $listener = self::getAllListener($config);
        $prefixHtml = self::onPrefixListener($listener);

        $check_key = $input_key = null;

        foreach ($config as $k => $v) {
            $key = $k;
            if (is_string($v))
                $key = $v;
            if ($v['type'] == 'file' && $method == 'edit') //上传文件在修改时不需要验证
                continue;

            $input_key[] = $key;
        }

        foreach ($check as $k => $v) {
            $key = $k;
            if (is_string($v))
                $key = $v;
            if (isset($v['value']) && $v['value'] == self::MAYBE)
                continue;

            if (in_array($key, $input_key)) {
                $check_key[] = $key;
// 				$check_name[] = ;
            }
        }

        //需js验证的表单name
        //$check_html = '<input type="hidden" id="check_html" value="' . implode('|', $check_key) . '"/>';
        $check_html = \Html::node('input',null, array(
            'type'=>'hidden','id'=>'check_html','value'=>implode('|', $check_key)
        ),true);

        $da = null;

        if ($edit_model && $id) {
            if(self::isRedisEngine($table)){
                $da = self::selectRedisData($table , $id);
            }
            else{
                $db = self::getDbByConfig($table);
                $da = $db->where("{$primary}='$id'")->find();
            }

            if (!$da)
                error(parseArg(self::ERROR_DATA_UNFIND, $table . "[{$primary}={$id}]"));
        }

        if ($id)
            $data[] = \Html::createInput('hidden', 'editid', $id); //如果是修改就创建一个hidden存放id

        if ($config && !is_array($config)) {
            $t = self::getDbByConfig($table);
            $config = $t->getDbFields();

        }

        foreach ($config as $k => $v) {
            if (is_string($v)) {
                $k = $v;
                $v = array('type' => 'text');
            } else if ($v['type'] == null) {
                $v['type'] = 'text';
            }

            $source = null;
            if (isset($v['data'])) {
                if (is_string($v['data'])) {
                    $source = self::parseFunc($v['data']);
                } else {
                    $source = $v['data'];
                }
            }


            if (isset($v['def']) && $v['def'] !== null) {
                if ($da[$k] === null) {
                    if(preg_match('/^\$(.+)\$$/',$v['def'],$preg))
                        $v['def'] = self::parseFunc( $preg[1] );
                    else if (strpos($v['def'], '$') === 0) {
                        $v['def'] = substr($v['def'], 1);
                        $v['def'] = $_REQUEST[$v['def']];
                    }

                    $da[$k] = $v['def'];
                }
            }

            $field_name = $k;
            if (is_string($field[$k])) {
                $field_name = $field[$k];
            } else if (is_array($field[$k])) {
                $field_name = $field[$k]['name'];
            }

            if(in_array($k ,$check_key))
                //'<span style="color:red" title="必须填写">★</span>';
                $field_name.= \Html::node('span','★',array('style'=>'color:red','title'=>'必须填写'));

            if (isset($v['func']) && $v['type'] == 'text') {
                $data[$field_name] = self::parseFunc($v['func'], $da[$k]);
            } else {
                $da[$k] = isset($v['func']) ? self::parseFunc($v['func'],$da[$k]) : $da[$k];
                $attr = self::getAllAttr($v);
                $attr = self::mergeBootstrapFormAttr($attr , $v['type']);

                $val = $da[$k];
                if($v['type'] == 'file' && !empty($v['config'])){ //文件上传
                    $file = new FileController();
                    $val = $file->show($v['config'] , $da[$k]);
                }
                $data[$field_name] = \Html::createInput($v['type'], $k, $val , $source , $attr );
            }

            //增加提示语句，提示语句从表的配置文件中的‘notice’读取
            if (isset($notice[$k]) && $notice[$k]) {
                $data[$field_name] .= '<div class="fright notice"><span class="glyphicon glyphicon-flag"></span><span> ' . $notice[$k] . '</span></div><div class="clear"></div>';
            }
        }

        foreach ($_GET as $k => $v) {
            $data[] .= \Html::createInput('hidden', $k, $v);
        }

        if ($edit_model) {
            $data[] .= \Html::createInput('hidden', 'isedit', '1'); //增加一个hidden，以确认是修改模式
        }

        $submit_string = $edit_model ? "修改" : "添加";
        $data[] = \Html::createInput('submit', 'submit', $submit_string , null ,
            self::mergeBootstrapFormAttr( array('class'=>'btn btn-primary') ,'submit'));

        $re = \Html::li($data , 'class="l"','class="r"');
        $re = \Html::node('ul',$re, 'class="list"');
        $re = \Html::form($re , U('Table/index', "table={$table}&method=sub"));

        $return['con'] = $prefixHtml . $re;
        $return['con'] .= $check_html . self::onSuffixListener($listener);

        return $return;
    }

    static public function getAllAttr($conf)
    {
        $readAttr = array('style','placeHolder','target','class','id','onclick','rows','cols');
        $attr = array();
        foreach($readAttr as $v)
        {
            if(!empty($conf[$v]))
                $attr[$v] = $conf[$v];
        }

        return $attr;
//        $re = '';
//        foreach($attr as $k=>$v)
//            $re .= $k .'="'.$v.'" ';
//        return $re;
    }

    /**
     * @param array $attr  配置的attr ，如：[ 'placeHolder'=>'xxx' , 'class'=>'test']
     * @param null $type  表单类型 ,如：[text|button|submit|select]
     */
    static public function mergeBootstrapFormAttr($attr , $type = null){
        switch($type){
            case 'submit':
                $classAttr = 'btn btn-default btn-sm';
                break;
            case 'radio':
                $classAttr = '';
                break;
            default:
                $classAttr = 'form-control input-sm';
        }

        if(!empty($attr['class']))
            $attr['class'] = $classAttr. ' ' .  trim($attr['class']);
        else
            $attr['class'] = $classAttr;
        return $attr;
    }

    static public function getAllowIds($table, $where)
    {
        $db = self::getDbByConfig($table);
        $primary = self::getPrimaryKey($table);
        $data = $db->field($primary)->where($where)->select();
        foreach ($data as $v) {
            $re[] = $v[$primary];
        }
        return $re;
    }


    //配置过滤器
    static private function configFilter($config, $method)
    {
        if (is_string($config))
            return $config;
        //根据当前方法是add或是edit过滤
        foreach ($config as $k => $v) {
            if (is_array($v) && isset($v['when']) && $v['when'] != $method)
                unset($config[$k]);
        }
        return $config;
    }


    static private function _sub($table) //添加或修改的提交
    {
        self::isTable($table);
        $method = 'add';
        $edit_model = $_REQUEST['isedit'] == 1 ? true : false; //修改模式  //判断是否是修改模式
        $id = is_numeric($_REQUEST['editid']) ? $_REQUEST['editid'] : 0;

        if ($edit_model) {
            $method = 'edit';
        }

        $db = self::getDbByConfig($table);
        $primary = self::getPrimaryKey($table);
        if ($edit_model) //修改模式
        {
            if(self::isRedisEngine($table)){
                $find_data = self::selectRedisData($table , $id);
            }
            else{
                $where = "{$primary}='{$id}'";
                $where = self::readRole("update", $where);
                $find_data = $db->where($where)->find();
            }

            if (!$find_data)
                error(parseArg(self::ERROR_DATA_UNFIND, $table . "[where={$where}]"));
        } else {
            $where = self::readRole("create", null);
        }


        $config = self::readConfigByRole($table, 'datasub');
        $field = self::readConfigField($table);
        $config = self::configFilter($config, $method);
        debug($config);
        $checkData = true;
        if (!$config) //未找到datasub配置文件的情况下
        {
            $allow = C($table . ".data");
            $checkData = false; //无需检查数据
        }

        if (!$config && !$allow)
            error('未找到配置文件');

        //如果config是字符串，那么直接把字符串当做函数去解析执行
        if (is_string($config)) {
            self::parseFunc($config);
            success();
        }

        /** 如果config 包含_prefix ,执行前缀函数 ,前缀函数中通过设置$_REQUEST[?] 的值，使之加入数据库
         *  如：要获取上传的文件（字段[ icon ]）的MD5值，可以在前缀函数中进行上传操作
         * ，并设置 $_REQUEST['md5'] = ??? , $_REQUEST['icon'] = ???
         */
        $listener = self::getAllListener($config);
        $prefixHtml = self::onPrefixListener($listener);

        if (!$config) {
            $t = self::getDbByConfig($table);
            $config = $t->getDbFields(); //读取所有字段
        }

        $operate = array();

        foreach ($config as $k => $v) {
            if (is_string($v)) {
                $k = $v;
            }

            if(strpos($k,'_') === 0)
            {
                $operate[$k] = $v;
                unset($config[$k]);
                continue;
            }

            $s[$k] = $_REQUEST[$k];
            if (is_array($v) && $v['func'] != null) {
                $s[$k] = self::parseFunc($v['func'], $s[$k]);
            }

            if (is_array($v) && $v['def'] != null) {
                if ($s[$k] === null)
                    $s[$k] = $v['def'];
            }

        }

        if ($checkData) {
            foreach ($s as $k => $v) {
                if ($config[$k]['value'] === 'maybe') //maybe的时候
                {
                    if ($v === null)
                        $s[$k] = '';
                    continue;
                }

                if ($v === '' || $v === null) {
                    $tempName = $field[$k];
                    if(is_array($tempName))
                        $tempName = $tempName['name'];
                    error("[" . $tempName . "]不得为空！");
                }
            }
        }

        $failedStr = $edit_model ? "修改失败！" : "添加失败！";

        if(self::isRedisEngine($table)){
            if($edit_model)
                $s[$primary] = $id;
            self::updateRedisData($table , $s) ? '' : error($failedStr );
        }
        else{
            if ($edit_model) {
                $db->where("{$primary}='{$id}'")->save($s)  ?  '' : error($failedStr );
            } else {
                $db->add($s) ? '' : error($failedStr );
            }
        }
        $suffixHtml = self::onSuffixListener($listener , $s);
        success( $suffixHtml. '操作成功', U(null, 'table=' . $table ));

    }

    public static function mysql2Redis($table = null){
        dump($table);
        dump(C('REDIS_CONFIG.'.$table));
        dump(self::readConfigByRole($table , 'redis'));
        exit;
    }

    static private function _del($table)
    {
        self::isTable($table);
        $primary = self::getPrimaryKey($table);
        $id = is_numeric($_REQUEST[$primary]) ? $_REQUEST[$primary] : 0;

//		$config = C($table.'.del');
        $config = self::readConfigByRole($table, 'del');
        if (!$config)
            error('未找到配置文件');

        if ($config !== true)
            error("配置文件中设置了不允许删除");

        if (!$id)
            error('未传入ID值');

        if(self::isRedisEngine($table)){
            $data = self::selectRedisData($table , $id);
        }
        else{
            $db = self::getDbByConfig($table);
            $wh = "{$primary}='{$id}'";
            $wh = self::readRole("delete", $wh);
            $data = $db->where($wh)->find();
        }
        if (!$data)
            error("未找到数据");

        if(self::isRedisEngine($table))
            self::delRedisData($table , $id) ? '' : error("删除失败！");
        else
            $db->where("{$primary}='{$id}'")->delete() ? '' : error("错误：" . $db->getLastSql());

        success("操作成功");
    }

    static private function _detail($table)
    {
        self::isTable($table);
        $config = C($table . '.detail');
        $field = C($table . '.field');
        if (!$config)
            $config = C($table . '.'.self::STRING_SHOW);
        else {
            $show_config = C($table . '.'.self::STRING_SHOW);
            foreach ($config as $k => $v) {
                if (is_string($v)) {
                    unset($config[$k]);
                    $config[$v] = $v;
                }
            }

            foreach ($show_config as $k => $v) {
                if (is_string($v)) {
                    unset($show_config[$k]);
                    $show_config[$v] = $v;
                }
            }
            $config = merge($show_config, $config);
        }

        if (!$config) error($table . '表的配置文件【detail 或者 show】 错误，请修改！');

        $primary = self::getPrimaryKey($table);
        $id = isset($_REQUEST[$primary]) ? $_REQUEST[$primary] : 0;
        $db = self::getDbByConfig($table);
        $wh = "{$primary}='{$id}'";
        $wh = self::readRole("read", $wh);
        $data = $db->where($wh)->find();

        if (!$data) error("ID错误");

        $datas = self::parseShow($table, array($data), $config);
        $list = $datas['data'][0];

        $re = \Html::twoColumnTable($datas['fields'], $list);
        $return['con'] = $re;


        if (is_array($config) && isset ($config['_with'])) {
            if (isset($config['_with']['where'])) {
                $link = self::parseLink($config['_with']['where'] );
                self::setGlobalWhere($config['_with']['table'], $link);
            }
            $with_re = self::_show($config['_with']['table']);
            $return['con'] .= "<br /><hr />" . $with_re['con'];
            $return['pager'] = $with_re['pager'];
        }
        return $return;
    }


    static private function createBaseNav($table)
    {
        $method = isset($_REQUEST['method']) ? $_REQUEST['method'] : self::STRING_SHOW;
        $primary = self::getPrimaryKey($table);
        $id = isset($_REQUEST[$primary]) && intval($_REQUEST[$primary]) ? $_REQUEST[$primary] : 0;

        $navs = array();
        $showconfig = self::readConfigByRole($table, self::STRING_SHOW);
        if ($showconfig) {
            $temp = "所有";
            if(isset($showconfig['_name']))
                $temp = $showconfig['_name'];
            $navs[$temp]['link'] = "table={$table}";
            $navs[$temp]['icon'] = "th";
        }

        if(self::isRedisEngine($table)){
            $temp = "Redis原型";
            if(isset($showconfig['_name']))
                $temp = $showconfig['_name'];
            $navs[$temp]['link'] = "table={$table}&method=redis";
            $navs[$temp]['icon'] = "th";
        }

        if ($conf = self::readConfigByRole($table, "data")) {
            if (isset($conf['_add']) && !$conf['_add']) {

            } else {
                $conf = self::configFilter($conf, 'add');
                if (is_string($conf) || count($conf) > 0) {
                    $navs['添加']['link'] = "table={$table}&method=add";
                    $navs['添加']['icon'] = "plus";
                }
            }
//			$list['添加'] = array('link'=>"table={$table}&method=add",'icon'=>'class="plus icon_image_black icon_transparent"');
        }

        $sortConf = self::readConfigByRole($table, self::STRING_SORT);
        if (!empty( $sortConf )) {
                $navs['排序']['link'] = "table={$table}&method=sort";
                $navs['排序']['icon'] = "sort";
        }

        if ($id && $method == 'add' && self::readConfigByRole($table, "data")) {
            $navs['修改']['link'] = "table={$table}&method=add&{$primary}='{$id}'";
            $navs['修改']['icon'] = "pencil";
//			$list['修改'] = array('link'=>"table={$table}&method=add&id={$id}",'icon'=>'class="pencil icon_image_black icon_transparent"');
        }
        if ($method == 'detail') {
            $navs['详细']['link'] = "table={$table}&method=detail&{$primary}='{$id}'";
            $navs['详细']['icon'] = "list";
//			$list['详细'] = array('link'=>"table={$table}&method=detail&id={$_REQUEST['id']}",'icon'=>'class="icon_bar icon_transparent"');
        }
// 		$fields = C("$table.field");
// 		if(isset($fields['status']))
// 			$list['审核'] = array('link'=>"table={$table}&method=status",'icon'=>'class="icon_gear icon_image_black icon_transparent"');
// 		if(isset($fields['enable']))
// 			$list['显示'] = array('link'=>"table={$table}&method=enable",'icon'=>'class="icon_gear icon_image_black icon_transparent"');
        /*
        $control = C("$table.control");
        if($control)
            $list['控制'] = array('link'=>"table={$table}&method=control",'icon'=>'class="icon_gear icon_image_black icon_transparent"');
        */

        return $navs;
    }

    static public function getDefaultTab()
    {
        $re['link'] = '';
        $re['icon'] = 'search';
        $re['selected'] = 1;
        return $re;
    }

    static public function createNav($table = null, $other_navs = null)
    {
        if ($table === null)
            $table = isset($_REQUEST['table']) ? $_REQUEST['table'] : '';

        $base_nav = array();
        if ($table)
            $base_nav = self::createBaseNav($table); //读取基本的导航栏

        $tabs_config = C("$table.tab"); //读取配置文件中tab的配置
        $tabs = null;
        if(is_array($tabs_config))
        {
            if(isset($tabs_config['func']))
            {
                $func_tabs = self::parseFunc($tabs_config['func']);
                unset($tabs_config['func']);
                $tabs_config = merge($func_tabs , $tabs_config);
            }

            foreach ($tabs_config as $k => $v) {
                if (!isset($v['link']))
                    continue;
                $v['attr'] = self::getAllAttr($v);
                $tabs[$k] = $v;
            }
        }


        $list = merge($base_nav, $tabs, $other_navs); //混合所有tab选项
        $def_icon = "search"; //默认ICON
        if (empty($list)) //nav为空的时候
        {
            //$re =  '<div class="nav"><span><div ' .$def_icon.'></div>当前</span></div>';
            return self::getDefaultTab();
        }

        foreach ($list as $k => $v) {
            $list[$k]['link'] = self::createLink(self::parseLink($v['link']));
            $list[$k]['icon'] = !empty($v['icon']) ? $v['icon'] : $def_icon;
            $list[$k]['selected'] = 0;
        }



        $currentUrl = BaseController::parseTPUrl();
        $find = false;

        foreach ($list as $k => $v) {
            $tempUrl = BaseController::parseTPUrl($v['link']);
            $diff = BaseController::compareTPUrl($currentUrl , $tempUrl , array('p','id'));
            if(empty($diff) || $diff['method'] == self::STRING_SHOW){
                $find = $k;
                break;
            }
        }

        if ($find)
            $list[$find]['selected'] = 1;
        else {
            $list['查询']['icon'] = $def_icon;
            $list['查询']['selected'] = 1;
        }
        return $list;
    }



    private static function parseFunctionChange(&$string , $leftToRight = true){
        $left = array('\,'=>'{abcdouhaocba}' , '\|'=>'{xyzshuhaozyx}');
        $right = array('{abcdouhaocba}'=>',','{xyzshuhaozyx}'=>'|');
        if($leftToRight)
            $string = str_replace(array_keys($left) , array_values($left) , $string);
        else
            $string = str_replace( array_keys($right) , array_values($right) , $string);
    }
    /**
     * 支持格式
     * 'func'=>'Classname|methodname=arg1,agr2,ar3{,},arg4,...'
     */
    static function parseFunc($func, $data = null , $allData = null)
    {
//        $temp = '{abcdedcba}'; //临时转换的内容
//        $func = str_replace("\,", $temp, $func); //将\转换为临时内容，等会分割后再转换回来

        self::parseFunctionChange($func);
        $func_arr = explode('&', $func);
        foreach ($func_arr as $func) {

            if (strstr($func, '|')) //针对格式（Data|get_da）
            {
                $func = explode('|', $func);
                if (count($func) != 2)
                    exit('函数解析错误！' . $func);
                $m = $func[0];
                if( strpos($m , 'Static') === 0){
                    $m = ucfirst( str_replace('Static','',$m) );
                    $m = '\Admin\Controller\\'.$m.'Controller' ;
                }
                else{
                    $m = A($m);
                }
                $a = $func[1];
                $data = self::parseFunction($a, $data, $m,  $allData);
            } else {
                $data = self::parseFunction($func, $data, null,  $allData);
            }
        }
        return $data;
    }

    /**
     * 针对格式（date=Y-m-d H:i:s,###）
     * @param  $func string 函数名称(系统函数，自定义函数，类方法)：date  md5  img
     * @param  $data string 数据,本身数据 ：如果函数名称包含###，则自动替换为$data
     * @param $class object  一个实例化的类对象，如： new CommonAction
     */

    private static  function parseFunction($func, $data = null, $class = null,  $allData = null) //
    {
        //函数和参数的混合体，以=分隔 ， eq: date=Y-m-d H:i:s,123554125351
        $func = explode('=', $func);

        if (count($func) == 1) {  //无参数
            if(is_string($class))
                return $class::$func[0]();
            else if (is_object( $class ) )
                return $class->$func[0]();
            else
                return $func[0]();
        }
        else if (count($func) == 2) {  //参数再以逗号分隔 ,eq :Y-m-d H:i:s,123554125351
            $arg_str = $func[1];
            $args = explode(',', $arg_str);
            foreach ($args as $k => $v) {
                if($v === '#*#')
                {
                    $args[$k] = $allData;
                    continue;
                }

                if ($v === '###')
                {
                    $args[$k] = $data;
                    continue;
                }
                if (0 === strpos($v, '$.')) //开始
                {
                    $temp = explode('.', $v);
                    if (count($temp) == 3) {
                        switch (strtolower($temp[1])) {
                            case 'get':
                                $args[$k] = $_GET[$temp[2]];
                                break;
                            case 'post':
                                $args[$k] = $_POST[$temp[2]];
                                break;
                            default:
                                break;
                        }
                    }
                }
                //if (strpos($args[$k], $temp) !== false)
                self::parseFunctionChange($args[$k] , false);
                //$args[$k] = str_replace($temp, ',', $args[$k]);
            }

            if ($class === null) {
                switch (count($args)) {
                    case 1:
                        return $func[0]($args[0]);
                    case 2:
                        return $func[0]($args[0], $args[1]);
                    case 3:
                        return $func[0]($args[0], $args[1], $args[2]);
                    case 4:
                        return $func[0]($args[0], $args[1], $args[2], $args[3]);
                    case 5:
                        return $func[0]($args[0], $args[1], $args[2], $args[3], $args[4]);
                    case 6:
                        return $func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
                    case 7:
                        return $func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6]);
                    case 8:
                        return $func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7]);
                    case 9:
                        return $func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8]);
                    case 10:
                        return $func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9]);
                }
            } else if(is_object($class)) {
                switch (count($args)) {
                    case 1:
                        return $class->$func[0]($args[0]);
                    case 2:
                        return $class->$func[0]($args[0], $args[1]);
                    case 3:
                        return $class->$func[0]($args[0], $args[1], $args[2]);
                    case 4:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3]);
                    case 5:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3], $args[4]);
                    case 6:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
                    case 7:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6]);
                    case 8:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7]);
                    case 9:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8]);
                    case 10:
                        return $class->$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9]);
                }
            }
            else if(is_string($class)){
                switch (count($args)) {
                    case 1:
                        return $class::$func[0]($args[0]);
                    case 2:
                        return $class::$func[0]($args[0], $args[1]);
                    case 3:
                        return $class::$func[0]($args[0], $args[1], $args[2]);
                    case 4:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3]);
                    case 5:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3], $args[4]);
                    case 6:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5]);
                    case 7:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6]);
                    case 8:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7]);
                    case 9:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8]);
                    case 10:
                        return $class::$func[0]($args[0], $args[1], $args[2], $args[3], $args[4], $args[5], $args[6], $args[7], $args[8], $args[9]);
                }
            }
        }
        exit('函数解析错误！' . $func);
    }


    static private function parseStringToSign($str) //将字符串转换为符号
    {
        switch ($str) {
            case 'eq':
                return '=';
            case 'in' :
                return 'in';
            case 'neq':
                return '!=';
            case 'gt':
                return '>';
            case 'lt':
                return '<';
            case 'egt':
                return '>=';
            case 'elt':
                return '<=';
            case 'like':
                return 'like';
        }
        return '';
    }

    static private function parseSignToChinese($str) //将符号转换为中文
    {
        switch ($str) {
            case '=':
                return '等于';
            case '!=':
                return '不等于';
            case '>':
                return '大于';
            case '<':
                return '小于';
            case '>=':
                return '大于等于';
            case '<=':
                return '小于等于';
            case 'like':
                return '包含';
            case 'in' :
                return "属于";
        }
        return '';
    }


    /**
     *   根据传过来的link 生成完整的url
     *
     *  模式1：  table=log_login&where=[uname]eq[###]  生成：__URL__?table=log_login&where=[uname]eq[###]
     *  模式2:      http 或者 www开始， 直接返回
     *        http://v.youku.com/v_show/id_###.html  生成： http://v.youku.com/v_show/id_###.html
     *  模式3：  “/”开始，
     *        /Operate/move?id=@@@   生成 __APP__/Operate/move?id=@@@
     */
    static public function createLink($link) //
    {
        //模式2 ,http开始：绝对url
        if (strpos($link, 'http://') === 0 || strpos($link, 'www.') === 0)
            return $link;

        if (strpos($link, '/') === 0) {
            $t = explode('?', trim($link, '/'));
            return U($t[0], empty($t[1]) ? '' : $t[1] );
        }
        //模式1
        return U('Table/index', $link);
    }

    static function parseLink($link, $olddata = '', $newdata = '', $allData = '') //解析link
    {
        if (!$link) return '';

        if (strpos($link, '!!!') !== false) //包含！！！的link，在值被函数转化之前生成链接
        {
            $link = str_replace("!!!", $olddata, $link);
        }

//        if (strpos($link, '@@@') !== false) //包含@@@ 的link，@@@被转换为 主键id字段
//        {
//            $link = str_replace("@@@", $allData['id'], $link);
//        }

        if (strpos($link, '###') !== false) //包含### 的link，在值被函数转化之后生成链接
        {
            $link = str_replace("###", $newdata, $link);
        }

//        if(preg_match('/@(.+)@/sU' , $link , $preg))
//        {
//            $link = str_replace('@'.$preg[1].'@' , $allData[$preg[1]] , $link);
//        }

        if(preg_match_all('/@(.+)@/sU',$link,$match) )
        {
            foreach($match[1] as $v)
            {
                if($allData && isset($allData[$v]))
                    $link = str_replace('@'.$v.'@' , $allData[$v] ,$link);
                else if(isset($_REQUEST[$v]))
                    $link = str_replace('@'.$v.'@' , $_REQUEST[$v] ,$link);
            }
        }

        $link = str_replace(' ', '%20', $link);
        return $link;
    }

    static function latelyView($str = '')
    {
        if ($str) {
            latelyView("$str");
            return "$str";

        }

        if (get_called_class() != __CLASS__) {
            $str = "管理后台";
            latelyView("$str");
            $result = "$str";
            return $result;
        }

        $table = $_REQUEST['table'];
        $method = isset($_REQUEST['method']) ? $_REQUEST['method'] : self::STRING_SHOW;
        $primary = self::getPrimaryKey($table);
        $id = isset($_REQUEST[$primary]) ? $_REQUEST[$primary] : 0;
        $table_name = C($table . '.name') ? C($table . '.name') : $table;
        $where = isset($_REQUEST['where']) ? $_REQUEST['where'] : '';
        switch ($method) {
            case 'add':
                if ($id)
                    $method_name = "修改";
                else
                    $method_name = "添加";
                break;
            case 'detail':
                $method_name = "详细";
                break;
            case 'redis':
                $method_name = 'Redis';
                break;
            case self::STRING_SHOW:
                if ($where)
                    $method_name = "查询";
                else
                    $method_name = "所有";
                break;
            case 'status':
                $method_name = "审核";
                break;
            case 'enable':
                $method_name = "显示";
                break;
            case 'sort':
                $method_name = '排序';
                break;
            default :
                $method_name = "";
        }

        if ($table_name && $method_name)
            latelyView("{$table_name} -> {$method_name}");


        return array('table'=> $table_name, 'method' => $method_name);
    }


    static private function readData($table, $config , $method = self::STRING_SHOW )
    {
        $selectField = array();
        $primary = self::getPrimaryKey($table);
        if(in_array('*',$config))
            $selectField = '*';
        else
        {
            //解析field
            if (isset($_REQUEST['field']))
                $selectField = $_REQUEST['field'];
            else {
                foreach ($config as $k => $v) {
                    if (strpos($k, '_') === 0) //下划线开始的都过滤掉，不属于field的范围
                        continue;

                    if (is_array($v) && in_array('no', $v)) //不属于select的范围
                        continue;

                    if (is_string($v))
                        $k = $v;

                    $temp = strToArray($k, '|');
                    $selectField = merge($selectField, $temp);
                }
                if(!in_array($primary,$selectField))  //必须包含主键
                    $selectField[] = $primary;
            }
        }
        //解析group
        $group = null;
        if (isset($_REQUEST['group']))
            $group = $_REQUEST['group'];

        //解析order
        $order = null;
        if (isset($_REQUEST['order']))
            $order = $_REQUEST['order'];
        $primary = self::getPrimaryKey($table);
        if (!$order)
            $order = isset($config['_order']) ? self::parseStringOrFunc($config['_order']) : "{$primary} desc";

        //解析pagesize
        $pagesize = null;
        if (isset($_REQUEST['pagesize']))
            $pagesize = $_REQUEST['pagesize'];
        if (!$pagesize) {
            if (isset($config['_pagesize'])) //解析pagesize
                $pagesize = $config['_pagesize'];
        }
        if (!$pagesize)
            $pagesize = C('PAGESIZE') ? C('PAGESIZE') : 20; //读取默认配置的 PAGESIZE

        //解析config里where,此处的where是绝对的，搜索的时候也会带上，用以限制用户的操作范围
        $conf_where = self::getConfWhere($table , $method);
        $conf_where = $conf_where ? $conf_where : array();

        //解析config里面的默认where，此where只会在show页面默认配置时读取
        $conf_def_where = self::getConfDefWhere($table , $method);
        $def_where = $conf_def_where ? $conf_def_where : array();


        //解析url里的where
        $url_where = self::parseUrlWhere();

        //解析global里的where
        $global_where = self::parseUrlWhere(self::getGlobalWhere($table));
        if ($global_where) //如果是搜索的话 那么删除默认where里的where
        {
            //unset($_GET['p']);
            $def_where = array();
            $url_where = array();
        }

        $wh =  merge($conf_where, $def_where, $url_where, $global_where);
        $wh = !empty($wh) ? $wh : null;

        $parameter = self::getGlobalWhere($table) ? merge($_GET, array('where' => self::getGlobalWhere($table))) : '';

        //根据进入REDIS处理
        if( self::isRedisEngine($table) )
        {
            $data = self::selectRedisData($table );
            if($data){
                import('@.Org.Page');
                $page = new \Page(count($data), $pagesize, $parameter);
                $data = array_slice($data , $page->firstRow , $page->listRows );
                $pager = $page->show();
            }
            else
                $pager = null;
        }
        else
        {
            $wh = implode(' and ', $wh);
            $db = self::getDbByConfig($table);
            if ($pagesize) {
                if ($group) {
                    $groups = $db->where($wh)->group($group)->select();
                    $count = count($groups);
                } else {
                    $count = $db->where($wh)->count();
                }

                import('@.Org.Page');
                $page = new \Page($count, $pagesize, $parameter);

                if ($group)
                    $data = $db->field($selectField)->where($wh)->group($group)->limit($page->firstRow, $page->listRows)->select();
                else
                    $data = $db->field($selectField)->where($wh)->order($order)->limit($page->firstRow, $page->listRows)->select();

                $pager = $page->show();
            } else {
                $data = $db->field($selectField)->where($wh)->group($group)->order($order)->select();
            }
        }


        //解析with
        if(isset($config['_with']))
        {
            foreach($data as $k=>$v)
            {
                if (isset($config['_with']['where'])) {
                    $link = self::parseLink($config['_with']['where'], null, null, $v );
                    self::deleteGlobalWhere($config['_with']['table']);
                    self::setGlobalWhere($config['_with']['table'], $link);
                }
                $with = self::_show($config['_with']['table']);
                $data[$k]['_with'] = $with['con'];
            }
        }

        $result['pager'] = $pager;
        $result['data'] = $data;
        $result['where'] = $wh;
        return $result;
    }


    public static function selectRedisData( $table  , $id = null ){

        $key = self::getRedisEngineKey($table);
        if( !empty($engineConfig['func']) )
        {
            //TODO 函数处理
        }
        else{
            switch( self::getRedisEngineType($table) ){
                case self::REDIS_TYPE_SET :
                    //getRedis()->set($table , array('1'=>array('id'=>'1','k'=>'a','v'=>'b')));
                    $re = getRedis()->get($key);
                    if( empty($id ))
                        return $re;
                    return $re[$id];
                case self::REDIS_TYPE_HSET :
                    if( empty($id))
                        return getRedis()->hGet($key);
                    return getRedis()->hGet($key , $id);
            }
        }
        return false;
    }


    public static function updateRedisData($table , $data){
        $key = self::getRedisEngineKey($table);
        $primary = self::getPrimaryKey($table);
        switch( self::getRedisEngineType($table) ){
            case self::REDIS_TYPE_SET :
                $re = getRedis()->get($key);
                if( !empty($data[$primary]))
                    $re[$data[$primary]] = $data;
                else{
                    $temp = max(array_keys($re))  ;
                    $temp++;
                    $data[$primary] = $temp;
                    $re[$temp] = $data;
                }
                return getRedis()->set($key , $re);
            case self::REDIS_TYPE_HSET :
                if( !empty($data[$primary])){
                    return getRedis()->hSet($key , $data[$primary] , $data) === false ? false : true;
                }

                $re = getRedis()->hGet($key);
                $temp = max(array_keys($re));
                $temp++;
                $data[$primary] = $temp;
                return getRedis()->hSet($key , $temp , $data);
        }
        return false;
    }


    public static function delRedisData($table , $id){
        if( empty($id))
            return false;
        $key = self::getRedisEngineKey($table);
        switch( self::getRedisEngineType($table) ){
            case self::REDIS_TYPE_SET :
                $re = getRedis()->get($key);
                unset($re[$id]);
                return getRedis()->set($key , $re);
            case self::REDIS_TYPE_HSET :
                return getRedis()->hDel($key , $id );
        }
        return false;
    }

    public static function getTableEngineConfig($table){
        $tempConfig = C($table.".".self::STRING_ENGINE);
        if(empty($tempConfig))
            return null;

        $config = C('REDIS_CONFIG.'.$table);
        if(empty($config['key']))
            $config['key'] = $table;
        if(empty($config['type']))
            error( parseArg(self::ERROR_ENGINE , $table.".".self::STRING_ENGINE) );
        return $config;

    }

    public static function getRedisEngineKey($table){
        $engineConfig = self::getTableEngineConfig($table);
        return empty( $engineConfig['key'] ) ? $table : $engineConfig['key'];
    }

    public static function getRedisEngineType($table){
        $re = self::getTableEngineConfig($table);
        return $re['type'];
    }

    public static function isMysqlEngine($table){
        $configEngine = self::getTableEngineConfig($table);
        return empty($configEngine) || $configEngine == 'mysql';
    }

    public static function isRedisEngine($table){
        $tempConfig = C($table.".".self::STRING_ENGINE);
        if(empty($tempConfig))
            return false;
        if( $tempConfig == 'redis')
            return true;
        return false;

    }

    public static function getConfWhere($table, $method = self::STRING_SHOW)
    {
        $config = self::readConfigByRole($table, $method);
        $conf_where = isset($config['_where']) ? self::parseStringOrFunc($config['_where']) : null;
        return $conf_where;

    }

    public static function getConfDefWhere($table , $method = self::STRING_SHOW )
    {
        $config = self::readConfigByRole($table, $method);
        $conf_where = isset($config['_def_where']) ? self::parseStringOrFunc($config['_def_where']) : null;
        return $conf_where;

    }


    private static function parseStringOrFunc($str)
    {
        if (is_array($str)) {
            if (isset($str['func']))
                return self::parseFunc($str['func']);
            return '';
        } else if (is_string($str)) {
            if (strpos($str, 'func://') === 0)
                return self::parseFunc(substr($str, 7));

            return self::parseIString($str);
//            $preg_str = '/\$(.+)\$/sU';
//            while (preg_match($preg_str, $str, $preg)) //$$之间的内容将被parseFunc
//            {
//                $temp = self::parseFunc($preg[1]);
//                $str = preg_replace($preg_str, $temp, $str, 1);
//            }
//            return $str;
        }
    }


    /**
     * 将一个字符串中 $.+$ 中的内容作为函数解析，  将 @.+@ 中内容作为 全局变量来解析
     * @param string $str    待解析的字符串
     * @param array $data  附加数据
     * @return string
     */
    public static function parseIString($str ,array $data = array())
    {
        if( preg_match_all('/\$(.+)\$/sU',$str , $match) )
        {
            foreach($match[1] as $v)
            {
                $str = str_replace('$'.$v.'$' , $v() ,$str);
            }
        }

        if(preg_match_all('/\@(.+)\@/sU',$str , $matched))
        {
            foreach($matched[1] as $v)
            {
                $str = str_replace('@'.$v.'@' , !empty($data[$v]) ? $data[$v] : $_REQUEST[$v] ,$str);
            }
        }
        return $str;
    }


    private static function parseShow($table, $data, $config)
    {
        $field = self::readConfigField($table); //合并默认field配置
        $hasToggle = self::isShowToggle($table);
        $primary = self::getPrimaryKey($table);
        foreach ($data as $k => $v) {
            $temp = self::readConfigByRole($table, 'data');
            if ($temp != null) { //检测有添加或修改的权限
                $conf = self::configFilter($temp, 'edit');
                if (is_string($conf) || count($conf) > 0) {  //有修改文件的配置
                    $link = U(null, "table={$table}&method=add&{$primary}={$v[$primary]}");
                    $data[$k]['caozuo1'] = '<a href="' . $link . '"><span class="glyphicon glyphicon-edit" title="修改"></span></a>';
                }
            }
            if (self::readConfigByRole($table, 'del') != null) {  //有删除的配置文件
                $link = U(null, "table={$table}&method=del&{$primary}={$v[$primary]}");
                $data[$k]['caozuo2'] = '<a href="' . $link . '"  onclick="javascript:return confirm(\'你确定要删除么？\')"><span class="glyphicon glyphicon-remove icon_delete" title="删除"></span></a>';
            }

            if ($hasToggle) {  //带有一个toggle的显示按钮
                $data[$k]['caozuo4'] = '<a data-toggle="modal" data-target="#toggle'.$k.'"><span class="glyphicon glyphicon-list toggle_btn" title="显示"></span></a>';
            }

            if (self::readConfigByRole($table, 'detail') != null) { //带有一个detail的显示按钮
                $detail_link = U(null, "table={$table}&method=detail&{$primary}={$v[$primary]}");
                $data[$k]['caozuo3'] = '<a href="' . $detail_link . '"><span class="icon_bar" title="详细"></span></a>';
            }

            $n = 5;
            $operate = self::readConfigByRole($table, 'operate'); //其他自定义操作按钮的解析
            if (is_array($operate)) {
                foreach ($operate as $k2 => $v2) {
                    if (is_array($v2)) {
                        if($v2['link'])
                        {
                            $v2['link'] = self::parseLink($v2['link'], null, null, $v);
                            $v2['link'] = self::createLink($v2['link']);
                        }
                        $link_icon = isset($v2['icon']) ?  $v2['icon']  : 'list';
                        $link_name = isset($v2['name']) ? $v2['name'] : '';
                        $link_target = isset($v2['link_target']) ? ' target="' . $v2['link_target'] . '"' : "";
                        $span = '<span class="glyphicon glyphicon-'.$link_icon.'" title="'.$k2.'"></span>';
                        if($v2['link'])
                            $data[$k]['caozuo' . $n++] = '<a href="' . $v2['link'] . '"' . $link_target . '>' . $span . '</a>';
                        else
                            $data[$k]['caozuo' . $n++] = $span ;

                    }
                }
            }
        }


        //解析可修改 edit 字段
        foreach ($config as $k => $v) {
            if (is_array($v) && ( isset($v['type']) && $v['type'] == 'edit') ) //该列可以修改
            {
                foreach ($data as $k2 => $v2) {
                    $ajax_url = U('Ajax/index',
                        array('table' => $table, 'key' => $k, $primary => $v2[$primary])
                    ) . '&value=';
                    $data[$k2][$k] = "<input type=\"text\" value=\"{$v2[$k]}\" data=\"{$v2[$k]}\" class=\"ajax_field\" ajax=\"{$ajax_url}\"/>";
                }
            }
        }


        $old_data = $data; //保留一个未被函数解析的data数据
        //解析config
        debug($config, "parseshow");
        $toggle = $attrs =  null;
        foreach ($config as $k => $v) {
            $f = null;
            if (is_string($v))
                $k = $v;

            if (strpos($k, '|')) //字段合并的情况,合并字段暂不能支持style等情况
            {
                $keys = explode('|', $k);
                foreach ($keys as $v2) {
                    $field_name = isset($field[$v2]) ? $field[$v2] : '';

                    if (is_array($field[$v2])) {
                        $field_name = $field[$v2]['name'];
                        $field_attr = self::getAllAttr($field[$v2]);
                    }

                    $f['name'] .= $field_name ? $field_name . " / " : null;
                    $f['attr'] .= isset($field_attr) ? $field_attr : '';
                }
                $f['name'] = trim($f['name']);
                if (substr($f['name'], "-1") == '/')
                    $f['name'] = substr($f['name'], 0, -1);
            } else {
                $f = isset($field[$k]) ? $field[$k] : null;
            }

            if ($f === null)
                continue;

            if (is_array($f)) {
                $fields[$f['name']] = $k;
                $attrs[$k]['attr'] = self::getAllAttr($f);
            } else {
                $fields[$f] = $k;
            }


            if (is_array($v)) {
                $attrs[$k]['attr2'] = self::getAllAttr($v);
                if (isset($v['func'])) //函数解析
                {
                    foreach ($data as $k2 => $v2) {
                        $data[$k2][$k] = self::parseFunc($v['func'], $data[$k2][$k] , $old_data[$k2]);
                    }
                }

                if (isset($v['link'])) {
                    foreach ($data as $k2 => $v2) {
                        $link = self::parseLink($v['link'], $old_data[$k2][$k], $data[$k2][$k], $data[$k2]);
                        $link = self::createLink($link);
                        if (isset($v['link_target']))
                            $target = "target=\"{$v['link_target']}\"";
                        $data[$k2][$k] = "<a href=\"{$link}\" {$target}>{$v2[$k]}</a>";
                    }
                }

                //type = toggle的处理
                if (isset($v['type']) && ($v['type'] == 'toggle' || $v['type'] == 'onlytoggle') ) // toggle处理
                {
                    foreach ($data as $k2 => $v2) {
                        $toggle[$k2][$k] = $data[$k2][$k];
                        if($v['type'] == 'onlytoggle')
                            unset($data[$k2][$k]);
                    }
                }

                //type = control的处理
                if(isset($v['type']) && $v['type'] == 'control'){
                    if (is_array($v['data']))
                        $control_data = $v['data'];
                    else
                        $control_data = self::parseFunc($v['data']);
                    foreach ($data as $k2 => $v2) {
                        $data[$k2][$k] = '<div class="btn-group">';
                        foreach ($control_data as $control_k => $control_v) {
                            if ((string)$control_v === (string)$v2[$k])
                                $class = "btn-primary";
                            else
                                $class = "";

                            $ajax_href = U('Ajax/index',
                                array('table' => $table, 'key' => $k, 'value' => $control_v, $primary => $v2[$primary])
                            );
                            $data[$k2][$k] .= "<span class=\"btn btn-default btn-sm ajax {$class}\" ajax=\"{$ajax_href}\">{$control_k}</span> ";
                        }
                        $data[$k2][$k] .= '</div>';
                    }
                }
            }
        }

        $flip_fields = array_flip($fields);
        foreach ($data[0] as $k => $v) //对最简单配置的支持
        {
            if (!isset($flip_fields[$k]) && !empty($control_keys) && !in_array($k, $control_keys) && !preg_match('/^caozuo\d$/', $k))
                $fields[$k] = $k;
        }
        $fields['操作'] = 'caozuo1|caozuo2|caozuo3|caozuo4|caozuo5|caozuo6|caozuo7|caozuo8|caozuo9';

        $result['fields'] = $fields;
        $result['data'] = $data;
        $result['toggle'] = $toggle ? $toggle : array();
        $result['attrs'] = $attrs;

        return $result;
    }

    static function createSearch($table)
    {
        self::isTable($table);
        $search = self::readConfigByRole($table, 'search');
        if (!$search) return '';

        $searchArray = $search_item =  array();
        $i = $find = 0;
        //判断是否是post请求
        $search_key = I('post.search_key', '');

        foreach ($search as $k => $v) {
            $find = $search_key === (string)$i ? 1 : 0;  //找到查询的类型
            $searchArray[$k]['active'] = $find ;
            $param  = $_GET;
            unset($param['p']);
            $searchArray[$k]['post'] = U('Table/index', $param);

            foreach ($v as $k2 => $v2) {
                $input_type = isset($v2['type']) ? $v2['type'] : 'text';
                $input_name = $v2['name'];

                $input_data = null;
                if (isset($v2['data'])) {
                    if (is_string($v2['data']))
                        $input_data = self::parseFunc($v2['data']);
                    else if (is_array($v2['data']))
                        $input_data = $v['data'];
                }
                if ($find) {
                    $field_name = preg_replace('/(.+)(__\d$)/', '$1', $input_name);
                    $input_post_data = I('post.' . $input_name, $v2['def']);
                    $sign_post_data = I('post.' . $input_name . '_sign', '');

                    if ($input_post_data != '' && $sign_post_data != '') {
                        if (isset($v2['func'])) {
                            $sub_post_data = self::parseFunc($v2['func'], $input_post_data);
                            $search_item[] = "[{$field_name}]{$sign_post_data}[{$sub_post_data}]";
                        } else
                            $search_item[] = "[{$field_name}]{$sign_post_data}[{$input_post_data}]";
                    }
                } else {
                    $input_post_data = empty($v2['def']) ? null : $v2['def'];
                    $sign_post_data = null;
                }

                $sign_def = isset($v2['sign_def']) ? $v2['sign_def'] : null;
                $sign = isset($v2['sign']) ? $v2['sign'] : 'eq';
                $signs = explode('|', $sign);
                $sign_data = null;
                foreach ($signs as $key => $val) {
                    $sign_key = self::parseSignToChinese(self::parseStringToSign($val));
                    if ($sign_key)
                        $sign_data[$sign_key] = $val;
                }

                if (count($sign_data) > 1){

                    $tempAttr = self::mergeBootstrapFormAttr( null , 'select');
                    $searchArray[$k]['item'][$k2] = \Html::createInput('select', $input_name . "_sign", $sign_post_data ? $sign_post_data : $sign_def, $sign_data , $tempAttr);
                }
                else{
                    $searchArray[$k]['item'][$k2] = \Html::createInput('hidden', $input_name . "_sign", $sign_data[$sign_key] );
                }

                $tempAttr = self::getAllAttr($tempAttr);
                $tempAttr = self::mergeBootstrapFormAttr( $tempAttr , $input_type );
                $searchArray[$k]['item'][] = \Html::createInput($input_type, $input_name, $input_post_data, $input_data , $tempAttr);
            }

            $searchArray[$k]['item'][] = \Html::createInput('hidden', 'search_key', $i);

            $tempAttr = self::mergeBootstrapFormAttr( null , 'submit' );
            $searchArray[$k]['item'][] = \Html::createInput('submit', 'search', '查询' , null , $tempAttr);

            if($find) //当前选中状态
                $searchArray[$k]['item'][] = '&nbsp;&nbsp;<a href="'.$_SERVER['REQUEST_URI'].'">取消</a>&nbsp;&nbsp;';

            $i++;
        }

        $search_str = '';
        if (count($search_item) > 0)
            $search_str = implode('|', $search_item);

        self::setGlobalWhere($table, $search_str);

        return $searchArray;
    }

    static function isShowToggle($table)
    {
        $conf = self::readConfigByRole($table, self::STRING_SHOW);
        foreach ($conf as  $v) {
            if (is_array($v) && isset($v['type']) && ($v['type'] == 'toggle' || $v['type'] == 'onlytoggle'))
                return true;
        }
        return false;
    }

    static function setGlobalWhere($table, $where)
    {
        $GLOBALS[$table][] = $where;
    }

    static function deleteGlobalWhere($table)
    {
        unset($GLOBALS[$table]);
    }

    static function getGlobalWhere($table = null)
    {
        if ($table === null)
            $table = $_GET['table'];
        if ( isset($GLOBALS[$table]) &&  is_array($GLOBALS[$table]) && count($GLOBALS[$table]) > 0)
            return implode('|', $GLOBALS[$table]);
        return null;
    }


    static function readRole($method, $wh, $table = null, $id = null)
    {
        $role_where = R("Role/role", array($method, $table, $id)); //解析角色权限
        return implode(' and ', merge($role_where, $wh));
    }

    static function parseUrlWhere($where = null)
    {
        $url_where = '';

        //解析url里的where
        if (!$where)
            $where = isset($_REQUEST['where']) ? trim($_REQUEST['where']) : '';

        if ($where) //带有where的显示
        {
            debug($where, '待解析的WHERE');
            $preg_str = '/\$(.+)\$/sU';
            while (preg_match($preg_str, $where, $preg)) //$$之间的内容将被parseFunc
            {
                $temp = self::parseFunc($preg[1]);
                $where = preg_replace($preg_str, $temp, $where, 1);
            }

            $where = explode("|", $where); //解析方式：[attr]eq[3]
            foreach ($where as $k => $v) {
                $preg_str = '/^\[(.+)\](.+)\[(.+)\]$/i';
                if (trim($v)) {
                    $preg = null;
                    $re = preg_match($preg_str, $v, $preg);

                    if (count($preg) == 4) {
                        $sign = self::parseStringToSign($preg[2]);
                        if ($sign) {
                            if ($sign == 'like') {
                                $url_where .= "`{$preg[1]}` {$sign} '%{$preg[3]}%' and ";
                            } else if ($sign == 'in') {
                                $url_where .= "`{$preg[1]}` {$sign} ({$preg[3]}) and ";
                            } else
                                $url_where .= "`{$preg[1]}` {$sign} '{$preg[3]}' and ";
                        }
                    }
                }
            }
            $url_where = trim($url_where);

            if (substr($url_where, -3) == 'and')
                $url_where = substr($url_where, 0, -3);
        }
        return $url_where;
    }

    static private function isTable($table)
    {
        if (empty($table))
            error(self::ERROR_TABLE_NAME);
    }

    static private function defaultFieldName()
    {
        $def = array(
            'id'=>'ID',
            'name'=>'名称',
            'url'=>'URL地址',
            'icon'=>'图标',
            'enable'=>'是否开启',
            'sort'=>'排序',
            'create_time'=>'创建时间',
            'update_time'=>'修改时间',
            'ip'=>'IP地址',
            'status'=>'状态'
        );
        $configDef = C('DEFAULT_FIELD_NAME');
        if(empty($configDef))
            return $def;
        return array_merge($def,$configDef);
    }


}
