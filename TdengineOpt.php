<?php

namespace App\Exceptions;

ini_set('date.timezone', 'Asia/Shanghai');

use Exception;

/**
 * 工厂模式方法调用
 */
class TdengineOpt
{
    static public  function factory($class_name)
    {
        switch (strtolower($class_name)) {
            case "restful":
                return new TdengineRestful();
                break;
                // case "other":
                //     return new other();
                //     break;
            default:
                break;
        }
    }
}


/**
 * TDengine Class
 * @user : jee.xue
 * @date : 2020-4-1 
 * TDengine 使用方法封装 PHP 采用RESTFUL方法
 */
class TdengineRestful implements TdengineOptInterface
{
    //设置参数
    private   $_tdIP = "127.0.0.1";
    private   $_tdPort = "6020";
    //TDengine默认的用户名和密码
    private  $_userName = "root";
    private  $_password = "taosdata";

    private $_databaseName = NULL;
    private $_tableName  = NULL;
    private $_stableName  = NULL;
    private $_tagValueStr  = '';

    //要查询的字段
    private $_fileds = "ltime";
    //条件
    private $_condition = NULL;
    //排序
    private $_orderby = NULL;
    //区间
    private $_limit = NULL;

    //定义采集站所需要的字段规则
    private   $_needRule  = [
        //不允许为空的
        "notnull" => [],
        //不允许重复的
        "notrepeat" => [],
    ];


    /**
     * 设置库
     * @databaseName 数据库名称
     * */
    public function setDatabase($databaseName)
    {
        $this->_databaseName = $databaseName;
        return $this;
    } //end func 

    /**
     * 设置表
     * @tableName 表名
     */
    public function setTable($tableName)
    {
        $this->_tableName = $tableName;
        return $this;
    } //end func 


    /**
     * 设置超级表
     * @param $stableName
     * @return $this
     */
    public function setSTable($stableName)
    {
        $this->_stableName = $stableName;
        return $this;
    }

    /**
     * 设置TAG值
     * @param $tagValue
     * @return $this
     */
    public function setTagValue($tagValue='')
    {
        $this->_tagValueStr = $tagValue;
        return $this;
    }

    /**
     * 设置字段规则
     */
    public function setRule($rule)
    {
        //校验
        if (
            !is_array($rule) ||
            !isset($rule["notnull"]) ||
            !isset($rule["notrepeat"])
        ) {
            die("err msg:rule must array,include fields,notnull,notrepeat");
        }
        $this->_needRule = $rule;
        return $this;
    }

    /**
     * 获取url
     * code = query 返回查询的URL
     * code = auth  返回AuthKey 的URL
     */
    private  function getUrl($code = "query")
    {
        //定义RESTURL
        $url = "http://" . $this->_tdIP . ":" . $this->_tdPort . "/rest/";
        //判断
        $url = (strtolower($code) == "auth") ? $url . "login/" . $this->_userName . "/" . $this->_password : $url . "sql";
        //返回
        return $url;
    } //end func getUrl

    /**
     * 获取TOKEN
     */
    private  function getToken()
    {
        return base64_encode($this->_userName . ":" . $this->_password);
    }

    /**
     * 获取授权码
     */
    public  function getAuthCode()
    {
        //获取
        $url = $this->getUrl("auth");
        //获取数据
        $data = Curl($url, "GET");
        //转成数组
        $dataList = json_decode($data, true);
        //返回参数
        if (!isset($dataList["desc"])) {
            return null;
        }
        //返回验证码
        return $dataList["desc"];
    } //end func getAuthCode

    /**
     * 执行SQL语句
     */
    public  function executeSql($sql = "")
    {
        if ($sql == "") {
            return [];
        }
        //获取TOKEN
        $token = $this->getToken();
        //设置HEADER
        $header = array("Authorization: Basic " . $token);
        //Restful 地址
        $url = $this->getUrl("query");
        //POST数据
        $data = Http_Post($url, $header, $sql);
        //返回数据
        return $data;
    } //end func addData

    /**
     * 新增日志数据
     * @table 表名
     * $params 参数
     * 必须遵循 KEY 为字段 VALUE 为值 
     */
    public  function addData($params = [])
    {
        //参数是否准确
        $this->checkDbTb();

        if (empty($params)) {
            die("error msg:params not empty!");
        }

        //拼接参数
        $fieldsValue = "'ltime',";
        $micorTime = getMsecTime();
        $strValue = "'" . getMsecToMescdate($micorTime) . "',";

        //不允许为空
        $notnull = $this->_needRule["notnull"];
        //判断是否命中不为空的值 
        if (is_array($notnull) && count($notnull) > 0) {
            $this->checkNotNullParam($notnull, $params);
        }

        //不允许重复的值 
        $norepeat = $this->_needRule["notrepeat"];
        //循环
        foreach ($params as $key => $value) {
            //拼接字段
            $fieldsValue .= "'{$key}',";
            //拼接数据
            $strValue .= "'{$value}',";
            //判断是否允许重复(谨慎设置,会有性能问题)
            if (in_array($key, $norepeat) && !$this->checkRepeat($key, $value)) {
                //校验返回
                die("err msg:column '{$key} = " . $value . "' value is repeat in TDengine!");
            }
        }
        //整理数据
        $sqlStr = "INSERT INTO {$this->_databaseName}.{$this->_tableName}(" . substr($fieldsValue, 0, strlen($fieldsValue) - 1) .
            ") VALUES(" . substr($strValue, 0, strlen($strValue) - 1) . ")";

        if($this->_stableName){
            $sqlStr = "INSERT INTO {$this->_databaseName}.{$this->_tableName} USING {$this->_databaseName}.{$this->_stableName} TAGS({$this->_tagValueStr})(" . substr($fieldsValue, 0, strlen($fieldsValue) - 1) .
                ") VALUES(" . substr($strValue, 0, strlen($strValue) - 1) . ");";
        }
        //var_dump($sqlStr);exit;
        //运行SQL语句
        $data = $this->executeSql($sqlStr);
        //返回
        return json_decode($data, true);
    } //end func 


    /**
     * 创建表
     * @dbname string 库名
     * @tbname string  表名
     * @columns [] 字段名 
     */
    public function createTableAsParams($columns = [])
    {
        //判断
        $this->checkDbTb();

        if (count($columns) == 0) {
            return "err msg:column is not null!";
        }

        //初始配置
        $sqlStr = "CREATE TABLE IF NOT EXISTS {$this->_databaseName}.{$this->_tableName}  (ltime TIMESTAMP,";

        //循环
        foreach ($columns as $key => $value) {
            //判断
            if (!isset($value["key"]) || !isset($value["type"])) {
                return "err msg:columns must [['key'=>'key name','type'=>'type value']]";
            }
            //组装
            $sqlStr .= "{$value["key"]} {$value["type"]},";
        }
        //最后组装 
        $sqlStr = substr($sqlStr, 0, strlen($sqlStr) - 1) . ")";
        //运行SQL语句
        $data = $this->executeSql($sqlStr);
        //返回
        return json_decode($data, true);
    } //end func createTableAsParams


    /**
     * @根据超级表创建表
     * @stablename string 超级表名
     * @tag 标签值
     */
    public  function createTableAsStable($stablename, $tag)
    {
        //判断
        $this->checkDbTb();

        if ($stablename == "" || is_null($stablename)) {
            return "err msg:stable is not null!";
        }

        if ($tag == "" || is_null($tag)) {
            return "err msg:tag is not null!";
        }

        //SQL 语句
        $sqlStr = "CREATE TABLE {$this->_databaseName}.{$this->_tableName}  USING {$this->_databaseName}.{$stablename} TAGS({$tag})";

        //运行SQL语句
        $data = $this->executeSql($sqlStr);
        //返回
        return json_decode($data, true);
    } //end func 



    //条件设置
    /**
     * 条件设置
     */
    public function setFields($fileds = [])
    {
        //设置查询字段
        $this->_fileds .= "," . implode(",", $fileds);
        return $this;
    }

    /**
     * WHERE 条件
     * key  字段名
     * value  值 
     * equal  eq
     * join  连接符
     */
    public function where($key, $value, $equal = "=", $join = "and")
    {
        //校验
        $this->checkEqual(strtolower($equal));
        //原STR
        $str = "";
        //eq
        switch (strtolower($equal)) {
            case "like":
                //LIKE的拼接
                $str = " ({$key} like '%{$value}%' OR  {$key} like '{$value}%' OR {$key} like '%{$value}')";
                break;
            case "in":
                //in value 必须是数组
                if (!is_array($value) || count($value) < 1) {
                    die("err msg:where use in values must be array,like [p1,p2,p3.......]");
                }
                //循环数组
                $length = count($value);
                //计数器
                $i = 1;
                foreach ($value as $kay => $val) {
                    if ($i >= $length)
                        $str .= " {$key}='{$val}' ";
                    else
                        $str .= " {$key}='{$val}'  OR ";
                    //累加计数器
                    $i++;
                }
                //拼接
                $str = $length > 1 ? " ({$str}) " : $str;
                break;
            case "between":
                //between 必须要有两个值value[0] 与 value[1]
                if (
                    !is_array($value) || count($value) < 2 ||
                    !isset($value[0]) || !isset($value[1])
                ) {
                    die("err msg:where use between values must be array,like [min,max]");
                }
                //拼接between的写法
                $str = " {$key} >= {$value[0]} AND {$key} <= {$value[1]} ";
                break;
            default:
                $str = " {$key}{$equal}'{$value}' ";
                break;
        }

        //条件值到少大于3位 (a>b)
        if (strlen(trim($this->_condition)) > 3) {
            if (strtolower($join) == "or") {
                $this->_condition .= " OR {$str}";
            } else {
                $this->_condition .= " AND {$str}";
            }
        } else {
            $this->_condition = $str;
        }

        //返回
        return $this;
    } //end func where


    /**
     * 排序
     * filed 字段
     * 有限制,只有TIMESTAMP字段才能排序,其它会报错
     * sort [desc asc]
     */
    public function orderBy($filed, $sort = "desc")
    {
        if (strtolower($sort) == "asc") {
            $this->_orderby = " ORDER BY {$filed} asc";
        } else {
            $this->_orderby = " ORDER BY {$filed} desc";
        }
        //返回
        return $this;
    } //end func 

    /**
     * limit 
     * start 开始
     * end  结束
     */
    public function limit($start = 0, $end = 10)
    {
        $this->_limit = " limit {$start},{$end}";
        return $this;
    } //end func 


    /**
     * 查询
     */
    public function query()
    {

        //校验数据库与表
        $this->checkDbTb();
        //拼接SQL
        $sqlStr = "SELECT {$this->_fileds} FROM {$this->_databaseName}.{$this->_tableName} ";

        //判断是否有条件
        if ($this->_condition != NULL && strlen($this->_condition) > 3) {
            $sqlStr .= " WHERE {$this->_condition}";
        }

        //判断是否order by
        if ($this->_orderby  != NULL && strlen($this->_orderby) > 7) {
            $sqlStr .= " {$this->_orderby}";
        }

        //判断是否有limit
        if ($this->_limit  != NULL && strlen($this->_limit) > 5) {
            $sqlStr .= " {$this->_limit}";
        } else { //默认1000
            $sqlStr .= " limit 0,1000";
        }

        //运行SQL语句
        $data = $this->executeSql($sqlStr);
        //返回
        return json_decode($data, true);
    }


    private function checkEqual($eq)
    {
        $eqList = ["=", ">", "<", ">=", "<=", "<>", "like", "in", "between"];
        if (!in_array($eq, $eqList)) {
            die("err msg:param equal must be  [=,>,<,>=,<=,<>,like,in,between]");
        }
        return true;
    }

    //检查数据库和表名
    private function checkDbTb()
    {
        if ($this->_tableName == null || $this->_databaseName == null) {
            die("error msg:table not null,database not null");
        }
    } //end func 

    //检查不为空
    private function checkNotNullParam($notnull = [], $params = [])
    {
        foreach ($notnull as $key => $value) {
            if (!isset($params[$value])) {
                die("err msg:rule not null column {$value} is not exists!");
            }
        }
    }

    /**
     * 检查是否有重复的值
     * key 字段
     * value 值 
     */
    private  function checkRepeat($key, $value)
    {
        //拼装SQL
        $sqlStr = "SELECT {$key} FROM {$this->_databaseName}.{$this->_tableName} WHERE {$key}='{$value}'";
        //执行SQL
        $data = json_decode($this->executeSql($sqlStr), true);
        //没有值 
        if (!isset($data["rows"])) {
            return true;
        }
        //没有返回报错
        return  intval($data['rows']) > 0 ? false : true;
    }
} //end class TDengine

//--------------------------------------------------------FUNCTION-----------------------------------------//
/**
 * 
 * 获取毫秒级别的时间戳
 */
function getMsecTime()
{
    list($msec, $sec) = explode(' ', microtime());
    $msectime =  (float) sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}

/**
 * 毫秒转日期
 */
function getMsecToMescdate($msectime)
{
    $msectime = $msectime * 0.001;
    if (strstr($msectime, '.')) {
        sprintf("%01.3f", $msectime);
        list($usec, $sec) = explode(".", $msectime);
        $sec = str_pad($sec, 3, "0", STR_PAD_RIGHT);
    } else {
        $usec = $msectime;
        $sec = "000";
    }
    $date = date("Y-m-d H:i:s.x", $usec);
    return $mescdate = str_replace('x', $sec, $date);
}

/**
 * 日期转毫秒
 */
function getDateToMesc($mescdate)
{
    list($usec, $sec) = explode(".", $mescdate);
    $date = strtotime($usec);
    $return_data = str_pad($date . $sec, 13, "0", STR_PAD_RIGHT);
    return $msectime = $return_data;
}


/**
 * HTTP_POST 
 */
function Http_Post($sUrl, $aHeader, $aData)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_URL, $sUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
    curl_setopt($ch, CURLOPT_POST, true);
    if (is_array($aData)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($aData));
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aData);
    }

    $sResult = curl_exec($ch);
    if ($sError = curl_error($ch)) {
        die($sError);
    }
    curl_close($ch);
    return $sResult;
}

/**
 * curl 函数
 * @param string $url 请求的地址
 * @param string $type POST/GET/post/get
 * @param array $data 要传输的数据
 * @param string $err_msg 可选的错误信息（引用传递）
 * @param int $timeout 超时时间
 * @param array 证书信息
 */
function Curl($url, $type, $data = false, &$err_msg = null, $timeout = 20, $cert_info = array())
{
    $type = strtoupper($type);
    if ($type == 'GET' && is_array($data)) {
        $data = http_build_query($data);
    }

    $option = array();

    if ($type == 'POST') {
        $option[CURLOPT_POST] = 1;
    }
    if ($data) {
        if ($type == 'POST') {
            $option[CURLOPT_POSTFIELDS] = $data;
        } elseif ($type == 'GET') {
            $url = strpos($url, '?') !== false ? $url . '&' . $data :  $url . '?' . $data;
        }
    }

    $option[CURLOPT_URL]            = $url;
    $option[CURLOPT_FOLLOWLOCATION] = TRUE;
    $option[CURLOPT_MAXREDIRS]      = 4;
    $option[CURLOPT_RETURNTRANSFER] = TRUE;
    $option[CURLOPT_TIMEOUT]        = $timeout;

    //设置证书信息
    if (!empty($cert_info) && !empty($cert_info['cert_file'])) {
        $option[CURLOPT_SSLCERT]       = $cert_info['cert_file'];
        $option[CURLOPT_SSLCERTPASSWD] = $cert_info['cert_pass'];
        $option[CURLOPT_SSLCERTTYPE]   = $cert_info['cert_type'];
    }

    //设置CA
    if (!empty($cert_info['ca_file'])) {
        // 对认证证书来源的检查，0表示阻止对证书的合法性的检查。1需要设置CURLOPT_CAINFO
        $option[CURLOPT_SSL_VERIFYPEER] = 1;
        $option[CURLOPT_CAINFO] = $cert_info['ca_file'];
    } else {
        // 对认证证书来源的检查，0表示阻止对证书的合法性的检查。1需要设置CURLOPT_CAINFO
        $option[CURLOPT_SSL_VERIFYPEER] = 0;
    }

    $ch = curl_init();
    curl_setopt_array($ch, $option);
    $response = curl_exec($ch);
    $curl_no  = curl_errno($ch);
    $curl_err = curl_error($ch);
    curl_close($ch);

    // error_log
    if ($curl_no > 0) {
        if ($err_msg !== null) {
            $err_msg = '(' . $curl_no . ')' . $curl_err;
        }
    }
    return $response;
}
