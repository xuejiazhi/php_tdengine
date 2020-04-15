<?php

namespace App\Exceptions;

/**
 * @接口定义
 * @author JEE.xue
 */
interface TdengineOptInterface
{
    //获取授权码
    public  function getAuthCode();
    //执行SQL语句
    public  function executeSql($sql = "");
    /**
     * 新增日志数据
     * @table 表名
     * $params 参数
     * 必须遵循 KEY 为字段 VALUE 为值 
     */
    public  function addData($params = []);
    /**
     * 创建表
     * @dbname string 库名
     * @tbname string  表名
     * @columns [] 字段名 
     */
    public  function createTableAsParams($columns = []);
    /**
     * @根据超级表创建表
     * @dbname string 库名
     * @tbname string  表名
     * @stablename string 超级表名
     * @tag 标签值
     */
    public  function createTableAsStable($stablename, $tag);
    /**
     * 设置库
     * @databaseName 数据库名称
     * */
    public function setDatabase($databaseName);
    /**
     * 设置超级表
     */
    public function setSTable($stableName);
    /**
     * 设置表
     * @tableName 表名
     */
    public function setTable($tableName);
    /**
     * 设置TAG值
     */
    public function setTagValue($tagValue='');
    /**
     * 设置字段规则
     */
    public function setRule($rule);
    /**
     * 条件设置
     */
    public function setFields($fileds = []);
    /**
     * WHERE 条件
     * key  字段名
     * value  值 
     * equal  eq
     * join  连接符
     */
    public function where($key, $value, $equal = "=", $join = "and");
    /**
     * 排序
     * filed 字段
     * 有限制,只有TIMESTAMP字段才能排序,其它会报错
     * sort [desc asc]
     */
    public function orderBy($filed, $sort = "desc");
    /**
     * limit 
     * start 开始
     * end  结束
     */
    public function limit($start = 0, $end = 10);
    /**
     * 查询
     */
    public function query();
}
