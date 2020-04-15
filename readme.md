#平常创建表方法：
```
        //变量：
        $params = [
                    [
                      "key" => "id",    //字段名
                    "type" => "bigint(15)"   //类型 / 长度
                     ],
                     [
                    "key" => "addr",
                    "type" => "nchar(50)"
                     ]
                  ];
 
调用方式：
        $data=TdengineOpt::factory("restful") //工厂模式实例，目前支持restful
            ->setDatabase("sdv_log")          //设置数据库
            ->setTable("wslog_6")             //设置哪个表
            ->createTableAsParams($params);  //传入参数，（格式如上）

        使用超级表创建表方法，需先创建超级表：
        //变量：
        $data=TdengineOpt::factory("restful")    //工厂模式实例 ，目前支持restful
                             ->setDatabase("sdv_log")     //设置数据库
                             ->setTable("wslog_6")        //设置哪个表
                            ->createTableAsStable("sdv_workstation_log", 4);  //超级表，和tag的值 

```
#正常插入数据：
```
          //定义采集站所需要的字段规则
          $wslog_NeedRule  = [
                              //不允许为空的
                              "notnull" => ["id", "opt", "sdv_stations_id"],
                              //不允许重复的
                              "notrepeat" => ["id"],
                              ];
 
          //定义要插入的字段及值（Key=>字段  Value=>值 ）
          $data = [
          "id" => $i,
          "opt" => "xxxxxx",
          "ip" => "127.0.0.1",
          "oprate_time" => time(),
          "sdv_stations_id" => "4"
          ];
          //工厂化实例 
          $datas=TdengineOpt::factory("restful") //工厂方式调用，目前支持restful
          ->setDatabase("sdv_log")   //设置数据库
          ->setTable("wslog_4")      //设置哪个表
          ->setRule($wslog_NeedRule)  //设置规则
          ->addData($data);           //增加数据   
 ```
   
#插入数据，没有子表的情况下自己创建子表：
```
      //定义采集站所需要的字段规则
      $wslog_NeedRule  = [
                            //不允许为空的
                            "notnull" => ["id", "opt", "sdv_stations_id"],
                            //不允许重复的
                            "notrepeat" => ["id"],
                         ];

      //定义要插入的字段及值（Key=>字段  Value=>值 ）
      $data = [
      "id" => $i,
      "opt" => "xxxxxx",
      "ip" => "127.0.0.1",
      "oprate_time" => time(),
      "sdv_stations_id" => "4"
      ];
      //工厂化实例 
      $datas=TdengineOpt::factory("restful") //工厂方式调用，目前支持restful
                  ->setDatabase("sdv_log")   //设置数据库
                  ->setSTable(“sdv_workstation_log”) //设置超级表，判断子表不存在会创建子表 
                  ->setTagValue(“000007”)      //设置标签
                  ->setTable("wslog_4")      //设置哪个表
                  ->setRule($wslog_NeedRule)  //设置规则
                  ->addData($data);           //增加数据
  ```
   
#查询功能
```
data = TdengineOpt::factory("restful") //工厂方式调用，目前支持restful
          ->setDatabase("sdv_log") //设置数据库
          ->setTable("sdv_workstation_log")     //设置哪个表
          ->setFields(["id","opt"])   //设置字段
          ->where("id",[1,2,3,4,5,6,7,8,9,10],"in")  //where 条件
          ->where("opt","xx","like") //where 条件
          ->limit(0,10) //设置LIMIT
          ->orderBy("ltime","desc")  //设置Order by 
          ->query();    //查询并返回结果
          
setFields
    setFields($params)  指定要查询的字段，如果不指定字段，默认查询ltime这个时序字段，加上字段后，会在字段后拼装字段
    例如：
    没有设置：
    Select ltime+(Fields) from tbName;
    setFields(["id","opt"])
    Select ltime,id,opt from tbName 
    
LIMIT
   limit($start = 0, $end = 10) 在链式调用中提供给SQL limit的作用，默认最大1000 

ORDERBY
   orderBy($filed, $sort = "desc")   在链式调用中提供给SQL 做排序使用，只支持timestamp。

WHERE
    where($key, $value, $equal = "=", $join = "and")  在链式调用中给SQL 提供WHERE条件，可以多个一起调用。

    $key   字段
    $value    值 
    $equal    条件运算符 包含有（[=,>,<,>=,<=,<>,like,in,between]）
       约束：当为like 时 生成条件为  key like ‘%value%’
       约束：当为in 时，$value为数组[p1,p2,p3]，生成条件为 （key=p1 or key=p2 or key=p3）
       约束：当为between时，$value为数组[start,end]，至少是两个值，第一个为 start;第二个为 end,生成的条件为  key>=start and key <=end
    $join   当链式调用多个条件的情况下，用 and 和 or 来链接多个条件
```


