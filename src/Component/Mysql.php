<?php 

namespace PHPSpider\Component;

use PDO;
use PHPSpider\Component\Mysql\Builder;
use PHPSpider\Component\Mysql\Querier;

#写操作方法
#insert
#insertGetId
#insertAll
#insertSelect
#delete
#update
#increase
#decrease
#读操作方法
#select
#find
#value
#column
#事务相关方法
#startTrans
#rollback
#commit
#聚合函数 暂不支持first 和 last
#avg
#count
#max
#min
#sum
#SQL相关链式操作
#distinct
#field
#table
#innerJoin
#leftJoin
#rightJoin 
#where
#group
#having
#order
#limit
#union
#lock
#comment
#force
#where条件相关链式操作
#whereOr
#whereAnd
#whereXor
#whereLike
#whereNotLike
#whereNull
#whereNotNull
#whereIn
#whereNotIn
#whereBetween
#whereNotBetween
#whereExp
#开关设置相关链式操作
#fetchSql
#fetchPdo
#strict
#数据相关链式操作
#data
#bind
#其他快捷链式操作
#page
#event

class Mysql
{    
    protected $config = [
        'host'            => '', //服务器地址 
        'port'            => '', //端口
        'dbname'          => '', //数据库名
        'username'        => '', //用户名
        'password'        => '', //密码
        'dsn'             => '', //连接dsn
        'params'          => [], //数据库连接参数
        'charset'         => 'utf8', //数据库编码默认采用utf8
        'prefix'          => '',     //数据库表前缀
        'debug'           => false,  //数据库调试模式
        'logfile'         => '',     //调试模式下日志文件
        'deploy'          => 0,      //数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
        'rw_separate'     => false,  //数据库读写是否分离 主从式有效
        'master_num'      => 1,      //读写分离后 主服务器数量
        'slave_no'        => '',     //指定从服务器序号
        'fields_strict'   => true,   //是否严格检查字段是否存在
        'result_type'     => PDO::FETCH_ASSOC, //数据返回类型
        'resultset_type'  => 'array', // 数据集返回类型
        'auto_timestamp'  => false, //自动写入时间戳字段
        'datetime_format' => 'Y-m-d H:i:s', //时间字段取出后的默认时间格式
        'sql_explain'     => false, //是否需要进行SQL性能分析
        'break_reconnect' => false, //是否需要断线重连
    ];
    
    protected $_querier;
    
    private static $_instance = [];
        
    public static function instance($config = [], $name = false)
    {
        if($name === true) {
	        $config = self::parseConfig($config);
	        return new self($config);
        }

        if (false === $name) {
            $name = md5(serialize($config));
        }
        
        if (!isset(self::$_instance[$name])) {
            $config = self::parseConfig($config);
            self::$_instance[$name] = new self($config);
        }

	    return self::$_instance[$name];
    }
    
    private static function parseConfig($config)
    {
        if (empty($config)) {
            $config = Config::get('mysql');
        } elseif (is_string($config) && false === strpos($config, '/')) {
            $config = Config::get($config);
        }
        
        if (is_string($config)) {
            return Builder::dsn($config);
        } else {
            return $config;
        }
    }
    
    public function __construct(array $config = []) 
    {
        if (!empty($config)) {
            $this->config = array_merge($this->config, $config);
        }

        $this->_querier = new Querier($this->config);
    }
    
    public function __call($name, $arguments) 
    {
        return call_user_func_array([$this->_querier,$name],$arguments);
    }
}

