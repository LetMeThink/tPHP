<?php
/**
 * Class Html 生成网页html的辅助类
 * @version 1.0.1
 */

class Html
{
    public static function node($nodeName , $nodeValue , $nodeAttr = null , $selfClose = false){
        $nodeAttrStr = $nodeAttr ?  self::parseAttr($nodeAttr) : '';
        if($selfClose)
            return "<{$nodeName} {$nodeAttrStr} />";
        return "<{$nodeName} {$nodeAttrStr}>{$nodeValue}</{$nodeName}>";
    }


    static function li($data , $keyAttr , $valueAttr)
    {
        $keyAttrStr = self::parseAttr($keyAttr);
        $valueAttrStr = self::parseAttr($valueAttr);
        $re = '';
        foreach ($data as $k => $v)
            if (is_numeric($k))
                if (strstr($v, 'type="hidden'))
                    $re .= $v;
                else
                    $re .= "<li><div {$keyAttrStr}>{$v}</div></li>";
            else
                $re .="<li><div {$keyAttrStr}>{$k}:</div><div {$valueAttrStr}>{$v}</div></li>";
        return $re;
    }

    static function form($html, $action, $method = 'post', $file = true, $attr = '')
    {
        if (!$file) {
            $re = '<form action="' . $action . '" method="' . $method . '" ' . $attr . '>' . $html . '</form>';
        } else {
            $re = '<form action="' . $action . '" method="' . $method . '" ENCTYPE="multipart/form-data" ' . $attr . '>' . $html . '</form>';
        }
        return $re;
    }

    /*生成table，数据格式如下：
     * $field = array(10) {
     *			  ["ID"] => string(2) "id"
     *			  ["登录名"] => string(5) "uname"
     *			  ["公司名称"] => string(7) "company"

     * $data = array(2)
     * 		 [0] => array(3) {
                ["id"] => string(1) "4"
                ["uname"] => string(82) "<a href="/app/admin.php/Table?table=log_login&where=[uname]eq[shenhe]" >shenhe</a>"
                ["pwd"] => string(32) "c3284d0f94606de1fd2af172aba15bf3"
                ["company"] => string(74) "<a href="/app/admin.php/Table?table=product&where=[attr]eq[4]" >shenhe</a>"
     * 		[1] => array(3) {
                ["id"] => string(1) "4"
                ["uname"] => string(82) "<a href="/app/admin.php/Table?table=log_login&where=[uname]eq[shenhe]" >shenhe</a>"
                ["pwd"] => string(32) "c3284d0f94606de1fd2af172aba15bf3"
                ["company"] => string(74) "<a href="/app/admin.php/Table?table=product&where=[attr]eq[4]" >shenhe</a>"
     *
     * $attr 是针对表格的起始行 （th 那一行）的格式
     * $attrs = array(
     *  	["table"] => array(1) {
                    ["attr"] => string(19) "style="width:100px""
                      }
              ["read"] => array(1) {
                ["attr"] => string(19) "style="width:100px""
              })
     */

    static function table($field, $data, $attrs, $toggle = null)
    {
        $mutil = false;
        $column_count = count($field);
        if (!empty($field['_mutil'])) {
            $mutil = $field['_mutil'];
            unset($field['_mutil']);
        }

        $re = '<table class="table table-hover"><tr>';
        if ($mutil)
            $re  .= '<th><input type="checkbox" id="table-select-all" /></th>';
        $toggle_field = array();
        foreach ($field as $k => $v) {
            if ($toggle && isset($toggle[0][$v])) //排除toggle的值
            {
                $toggle_field[$v] = $k; //将内容加入toggle_field
                //unset($field[$k]);  //删除field
                //continue;
            }
            if(!isset($data[0][$v]))
                continue;


            $attr = isset($attrs[$v]['attr']) ? $attrs[$v]['attr'] : '';
            $attr = self::parseAttr($attr);
            $re  .= '<th ' . $attr . '>' . $k . '</th>';
        }
        $re  .= '</tr>';

        $toggle_html = '';
        foreach ($data as $k => $v) {
            $re  .= '<tr>';
            if ($mutil)
                $re  .= "<td><input type=\"checkbox\" name=\"id[]\" value=\"{$v['id']}\" /></td>";
            foreach ($field as $v2) {
                $attr2 = isset($attrs[$v2]['attr2']) ? $attrs[$v2]['attr2'] : '';
                $attr2 = self::parseAttr($attr2);
                $re  .= '<td ' . $attr2 . '>';
                $exp = explode('|', $v2);
                if (count($exp) > 1) {
                    foreach ($exp as $v3) {
                        $re  .= isset($v[$v3]) ? $v[$v3] . ' ' : '';
                    }
                } else {
                    $re  .= isset($v[$v2]) ? $v[$v2] : '';
                }
                $re  .= '</td>';
            }
            $re  .= '</tr>';

            //dump($toggle);
//			if($toggle)
//			{
//				$toggle_html .="<div class=\"toggle\">" ;
//				$toggle_html .= "<div class=\"operate\"><span class=\"icon_delete\"></span></div>" ;
//				$toggle_html .="<table class=\"two_column\">";
//				foreach($toggle[$k] as $toggle_k=>$toggle_v)
//				{
//					$toggle_html .="<tr>";
//					$toggle_html .="<td>". $toggle_field[$toggle_k]."</td>";
//					$toggle_html .="<th>". $toggle_v."</th>";
//					$toggle_html .= "</tr>";
//				}
//				$toggle_html .="</table></div>";
//			}

            if (!empty($toggle)) {
                $tempBody = "<table class=\"table table-bordered table-condensed\">";
                foreach ($toggle[$k] as $toggle_k => $toggle_v) {
                    $tempBody .= "<tr>";
                    $tempBody .= "<td class=\"min_width_100\">" . $toggle_field[$toggle_k] . "</td>";
                    $tempBody .= "<th>" . $toggle_v . "</th>";
                    $tempBody .= "</tr>";
                }
                $tempBody .= "</table>";
                $toggle_html .= self::templeteToggle("toggle{$k}", "ID:{$v['id']}", $tempBody);
            }

        }

        if ($mutil) //多选框
        {
            $re  .= <<<EOF
        <tr><td colspan='{$column_count}'>  <input type="submit" name="del" value="删除选中项" class="btn btn-primary btn-sm" onclick="return confirm('确定要删除选中项目?');" /></td></tr>
        <script>
        $("#table-select-all").click(function(){
             if($(this).is(':checked'))
                    $("input[type='checkbox']",$(this).parent().parent().parent()).prop("checked","true");
                else
                    $("input[type='checkbox']",$(this).parent().parent().parent()).removeAttr("checked");
        });
        </script>
EOF;
        }
        $re  .= '</table>';
        $re  .= $toggle_html;
        return $re ;
    }

    static function sortTable($field, $data, $attrs)
    {
        foreach ($field as $k => $v) {
            if (strstr($v, '|')) {
                $exp = explode('|', $v);
                foreach ($exp as $v2) {
                    foreach ($data as $data_k => $data_v) {
                        if (isset($data_v[$v2]))
                            $data[$data_k][$v] .= '【' . $data_v[$v2] . '】 ';
                    }

                }
            }
            $fields[$v] = $k;
        }

        $h = array();
        foreach ($data as $k => $v) {
            $temp = '<table><tr>';
            foreach ($v as $k2 => $v2) {
                $tempAttr = empty($attrs[$k2]['attr2']) ? '' : $attrs[$k2]['attr2'];
                $tempAttr = self::parseAttr($tempAttr);
                if ($k2 == 'id')
                    $temp .= "<td {$tempAttr}><input type=\"hidden\" name=\"id[]\" value=\"{$v2}\"/>{$fields[$k2]}:<span>{$v2}</span></td>";
                else if (isset($fields[$k2]))
                    $temp .= "<td {$tempAttr}>{$fields[$k2]}:<span>{$v2}</span></td>";
            }
            $temp .= "<td class=\"drag\"><span class=\"glyphicon glyphicon-hand-up\"></span>拖动</td>";
            $h[] = $temp . '</tr></table>';
        }

        $re = '<ul id="sortable" class="sort_table">';
        foreach ($h as $v)
            $re .= "<li>{$v}</li>";
        $re .= '</ul>';
        $re .= "<hr />" . self::createInput('submit','submit','提交排序',null,array('class'=>'btn btn-primary'));
        $re .= <<<EOT
            <script>
                $(function() {
                    $( "#sortable" ).sortable();
                    $( "#sortable" ).disableSelection();
                });
            </script>
EOT;
        return "<form method=\"post\">$re</form>";
    }

    static function twoColumnTable($field, $data)
    {
        foreach ($field as $k => $v) {
            if (strpos($v, '|') !== false) {
                $exp = explode('|', $v);
                foreach ($exp as $v2) {
                    if (isset($data[$v2])) {
                        $data[$v] .= $data[$v2] . ' ';
                    }
                }
            }
            $fields[$v] = $k;
        }

        $re  = '<table class="table">';
        foreach ($data as $k => $v) {
            if (isset($fields[$k]))
                $re .= "<tr><td>{$fields[$k]}</td><th>{$v}</th></tr>";
        }
        $re .= '</table>';
        return $re;
    }

    /**
     * 根据传入的类型，名称，值，数据 生成对应的input的html
     * @param string $type , 类型，如：text hidden submit file textarea checkbox ……
     * @param string $name ，名称，如：<input type="text" name="$name
     * @param string $value ，值，如：<input type="text" name="$name" value="$value"
     * @param array|null $data ，数据：当$type为select或checkbox时，需要传递数据。如：array('下载'=>'1','分享'=>'2','邀请'=>'3')
     * @param array|null $attr ,  属性：array('size'=>'60','rows'=>'6'),将被解析为 size="60" rows="6"
     * @return string
     */
    static function createInput($type, $name = null, $value = null, $data = null, $attr = null)
    {
        if (!$type)
            return '';


        $attributes = self::parseAttr($attr, $type);
        switch ($type) {
            case 'text':
            case 'hidden':
            case 'file':
                $html = '<input type="' . $type . '"';
                if ($name)
                    $html .= ' name="' . $name . '"';
                if ($value !== null)
                    $html .= ' value=\'' . $value . '\' ';
                $html .= $attributes . ' />';
                $html .= $type == 'file' && $value ? '<br/><span>已上传：' . $value . "</span>" : '';
                return $html;
            case 'submit' :
            case 'button':
                return '<button name="' . $name . '" type="' . $type . '" ' . $attributes . '>' . $value . '</button>';
            case 'readonly':
                $html = '<input type="hidden"';
                if ($name)
                    $html .= ' name="' . $name . '"';
                if ($value !== null)
                    $html .= ' value="' . $value . '"';
                $html .= ' />' . $value;
                return $html;
//                return $value;

            case 'textarea':
                $html = '<textarea ' . $attributes;
                if ($name)
                    $html .= ' name="' . $name . '">';
                if ($value)
                    $html .= $value;
                $html .= '</textarea>';
                return $html;
            case 'select':
            case 'checkbox':
                if (!is_array($data))
                    return '无数据';
                return self::$type($name, $data, $value, $attributes);

            case 'radio':
                return self::$type($name, $data, $value, $attributes);

            case "mutilText":
                return self::mutil('text' , $name, $value, $attributes);

            case "mutilTextarea":
                return self::mutil('textarea' , $name, $value, $attributes);

            case "mutilFile":
                return self::mutil('file' , $name, $value, $attributes);


            case 'date':
                static $js_date_import = 0;
                $v = '';
                if ($value) {
                    if (is_numeric($value) && $value > 1000) {
                        $v = ' value="' . date('Y-m-d', $value) . '" ';
                    } else {
                        $v = ' value="' . $value . '" ';
                    }
                }
                $html = '<input type="text" name="' . $name . '"' . $v . ' onclick="fPopCalendar(event,this,this)" onfocus="this.select()" readonly="readonly" />';
                if ($js_date_import == 0) {
                    $html .= '<script lanugae="javascript" src="/Public/js/admin/date.js"></script>';
                    $js_date_import = 1;
                }
                return $html;

            case 'datetime':
                static $jquery_ui_import = 0;
                $v = '';
                if ($value) {
                    if (is_numeric($value) && $value > 1000) {
                        $v = ' value="' . date('Y-m-d H:i:s', $value) . '" ';
                    } else {
                        $v = ' value="' . $value . '" ';
                    }
                }
                $html = '<input type="text" name="' . $name . '"' . $v . ' class="datetimetext" />';
                if ($jquery_ui_import == 0) {
                    $html .= '<script langugae="javascript" src="Public/js/admin/datetimepicker.js"></script>';
                    //onclick="fPopCalendar(event,this,this)" onfocus="this.select()"
                    $html .= <<<EOF
                <script>
                    $(function(){
                        $('.datetimetext').datetimepicker({dateFormat:'yy-mm-dd',});
                    });
                </script>
EOF;
                    $jquery_ui_import = 1;
                }
                return $html;

            case 'editor':
                if ($attributes == '')
                    $html = '<textarea rows="5" cols="60"';  //默认大小
                else
                    $html = '<textarea ' . $attributes;

                if ($name)
                    $html .= ' name="' . $name . '">';
                if ($value)
                    $html .= $value;
                $html .= '</textarea>';
                static $import;
                if (!$import) {
                    $html .= '<script src="/Public/ckeditor/ckeditor.js" type="text/javascript"></script>';
                    $import = true;
                }
                $html .= '<script type="text/javascript">
                            var editor = CKEDITOR.replace("' . $name . '");
                            ckfinder_path = "Public/ckfinder";
                            CKFinder.SetupCKEditor(editor, "Public/ckfinder/");
                        </script>';
                return $html;


            case 'selectmove':
                static $move_select_count = null;
                $h = '';
                if ($move_select_count === null) {
                    //$h .= '<script type="text/javascript" src="'.__PUBLIC__.'/js/admin/select_move.js"></script> ';
                    $move_select_count = 1;
                } else {
                    $move_select_count++;
                }
                $id_prefix = "move-select-{$move_select_count}-";
                $base_id_attr = $attributes . " id=\"{$id_prefix}base\"";
                $con_id_attr = $attributes . " id=\"{$id_prefix}container\"";

                //$data 为存储在待选select的数据  ，$save_data为以选中的数据
                $def = strToArray($value);
                $save_data = array();
                foreach ($data as $k => $v) {
                    if (in_array($v, $def)) {
                        $save_data[$k] = $v;
                        unset($data[$k]);
                    }
                }

                //原始数据的select
                $base = "<div class=\"select_move_base\"> " . self::select("{$name}_old", $data, null, $base_id_attr, true) . "</div>";

                //按钮的内容
                $btn = <<<EOT
                <div class="select_move_btn">
                    <a id="{$id_prefix}in"> > </a>
                    <a id="{$id_prefix}fill"> >> </a>
                    <a id="{$id_prefix}out"> < </a>
                    <a id="{$id_prefix}empty"> << </a>
                </div>
EOT;
                //保存的select
                $save = "<div class=\"select_move_container\"> " . self::select("{$name}[]", $save_data, null, $con_id_attr, true) . "</div>";

                $html = $h . '<div id="select_move_' . $move_select_count . '" class="select_move">' . $base . $btn . $save . "</div>";
                $html .= <<<EOT
            <script>
                $(function(){
                    $("#select_move_{$move_select_count}").moveSelect({prefix : "#{$id_prefix}"});
                });
            </script>
EOT;
                return $html;
            case 'ajaxtext':
                return self::ajaxText($name, $value, $attributes);
            default:
                return '';
        }
    }

    private static function mutil($type , $name , $data , $attributes){
        switch($type){
            case "text":
            case "file":
                $emptyInput = "<input type=\"{$type}\" name=\"{$name}[]\" class=\"form-control input-sm\" />";
                $defValueInput = "<input type=\"{$type}\" name=\"{$name}[]\" value=\"__REPLACE__\" class=\"form-control input-sm\"/>";
                break;
            case "textarea":
                $emptyInput = "<textarea name=\"{$name}[]\" class=\"form-control input-sm\"></textarea>";
                $defValueInput = "<textarea name=\"{$name}[]\" class=\"form-control input-sm\">__REPLACE__</textarea>";
                break;
            default:
                return '';
        }

        $emptyItem = "<li>{$emptyInput}<input type=\"button\" name=\"{$name}-remove\" value=\"删除\" /></li>";
        if(!empty($data)){
            $data = json_decode($data , true);
            $defaultItem = '';
            foreach($data as $k=>$v){
                $valueInput = str_replace('__REPLACE__' , $v , $defValueInput);
                $defaultItem .= "<li>{$valueInput}<input type=\"button\" name=\"{$name}-remove\" value=\"删除\" /></li>";
            }
        }else{
            $defaultItem = $emptyItem;
        }

        $template = <<<EOF
    <div class="{$name}-wrapper">
        <input type="button" name="{$name}-add" value="添加" />
        <ul class="{$name}-ul">
            {$defaultItem}
        </ul>
        <script>
            $("input[name={$name}-add]").on("click",function(){
                $(".{$name}-ul").append('{$emptyItem}');
            });
            $(".{$name}-wrapper").on("click","input[name={$name}-remove]",function(){
                $(this).parent().remove();
            });
        </script>
    </div>
EOF;
        return $template;
    }

    /**
     * 将array("rows"=>"100","cols"=>"100")  转换为 rows="100" cols="100"
     * @param mixed $attr
     * @param mixed $type
     * @return string
     */
    private static function parseAttr($attr = null, $type = null)
    {
        if (!is_array($attr))
            return $attr;

        $re = '';
        if ($type)
            switch ($type) {
                case "textarea":
                    if (empty($attr['rows']))
                        $attr['rows'] = 5;
                    break;
            }

        foreach ($attr as $k => $v)
            $re .= $k . '="' . $v . '" ';
        return $re;
    }

    /**
     *
     * @param string $name
     * @param array $data
     * @param unknown_type $value
     * @param unknown_type $attr
     * @return string
     */
    private static function checkbox($name, $data, $value = null, $attr = null)
    {
        $arr = array();
        if (is_string($value))
            $arr = strToArray($value);
        else
            $arr = $value;

        $html = '<table class="checkbox"><tr>';
        $html .= '<td><input type="checkbox"  id="selectall' . $name . '"/><label for="selectall' . $name . '">全选</label></td>';

        $i = 1;
        foreach ($data as $k => $v) {
            $v = (string)$v;
            $check = $td = '';
            if (is_array($arr) && in_array($v, $arr))
                $check = " checked=checked";
            $td = 'class="selected_td"';
            $html .= '<td><input id="' . $name . $v . '" type="checkbox" name="' . $name . '[]" value="' . $v . '"' . $check . '/> <label for="' . $name . $v . '">' . $k . '</label></td>';
            if ($i++ % 8 == 0)
                $html .= "</tr><tr>";
        }
        $html .= "</tr></table>";

        $html .= <<<EOF
        <script>
        $(function(){
            $("#selectall{$name}").click(function(){
                if($(this).is(':checked'))
                    $("input[type='checkbox']",$(this).parent().parent()).prop("checked","true");
                else
                    $("input[type='checkbox']",$(this).parent().parent()).removeAttr("checked");
            });
        });
        </script>
EOF;
        return $html;

    }


    private static function select($name, $data, $value = null, $attr = null, $mutil = false)
    {
        $mutil_html = $mutil ? 'multiple="multiple" ' : '';
        $def = $mutil || count($data) == 1 ? '' : '<option value="">请选择</option>';
        $html = '<select ' . $mutil_html . 'name="' . $name . '" ' . self::parseAttr($attr) . '>' . $def;
        foreach ($data as $k => $v) {
            $selected = '';
            if (is_string($value) && $value == $v)
                $selected = " selected=selected";

            $html .= '<option  value="' . $v . '" ' . $selected . '>' . $k . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private static function radio($name, $data, $value, $attr = null)
    {
        if (empty($data) || !is_array($data))
            $data = array('是' => '1', '否' => '0');

        $html = '';
        foreach ($data as $k => $v) {
            $checked = (string)$value === (string)$v ? "checked=checked" : "";
            $ratioHtml = '<input type="radio" name="' . $name . '" value="' . $v . '" ' . $checked . ' ' . self::parseAttr($attr) . '/>';
            $html .= '<label class="radio-inline">' . $ratioHtml . $k . '</label> ';
        }
        return $html;
    }


    /**
     * @param $name
     * @param $value
     * @param $attr
     * $attr 必须配置 : url="url地址"  :
     * 如：http://gcenter.joymeng.com/index.php?m=Home&c=Table&a=index&table=channel_mst&val=
     * 注意：url的末尾必须定义当前text的值的键，在进行ajax提交时，系统会自动把当前text的值串联到url地址里面，并进行相应请求
     */
    private static function ajaxText($name, $value, $attr)
    {
        static $import;
        $script = '';
        if ($import === null) {
            $script = <<<EOT
                <script>
                    $(function(){
                        $(".ajaxtext").ajaxError(function(){
                            loading(0);
                            notice("ajax请求错误");
                        });
                        $(".ajaxtext").blur(function(){
                            var the = this;
                            var data = $(this).attr("data");
                            var val = $(this).val();
                            var url = $(this).attr("url");

                            if(!url || !val)
                                return ;

                            if(val == data)
                            {
                                notice("未修改");
                                return ;
                            }
                            url += val;
                            debug(url);
                            var load = setTimeout('loading(1)',300);
                            $.post(url,'',function(data){
                                $(the).attr("data" , val);
                                clearTimeout(load);
                                loading(0);
                                notice(data.info);
                            },'json');
                        });
                    });
                </script>
EOT;
            $import = true;
        }

        $attr = self::parseAttr($attr);
        $re = "<input class=\"ajaxtext\" type=\"text\" value=\"{$value}\" name=\"{$name}\" data=\"{$value}\" {$attr}/> {$script}";
        return $re;
    }


    private static function templeteToggle($id, $title, $body)
    {
        return <<<EOF
        <div class="modal fade" id="{$id}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span aria-hidden="true">&times;</span><span class="sr-only">Close</span></button>
                <h4 class="modal-title" id="myModalLabel">
                    {$title}
                </h4>
              </div>
              <div class="modal-body">
                {$body}
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">关闭</button>
              </div>
            </div>
          </div>
        </div>
EOF;
    }
}