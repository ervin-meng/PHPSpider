<?php
namespace PHPSpider\Component;

class Redis
{
    static  $_instance    = [];
    private $_options     = ['multi_idc' => false, 'auth' => true];
    private $_localRedisOp;
    private $_allRedisOp  = [];
    private $_redisWFuncs = ['set','setex','setnx','delete','mset','hset', 'incr', 'incrBy', 'decr','decrBy'];

    public static function getInstance($servers = array()) {

        $serverKey = md5(serialize($servers));

        if (!isset(self::$_instance[$serverKey])) {
            self::$_instance[$serverKey] = new self($servers);
        }

        return self::$_instance[$serverKey];
    }

    public function __construct($servers=array())
    {
        $pos = 0;
        
        foreach ($servers as $server) {
            $this->_allRedisOp[$pos]['host']   = $server;
            $this->_allRedisOp[$pos]['rRedis'] = new \Redis();
            $this->_allRedisOp[$pos]['wRedis'] = new \Redis();
            $pos ++;
        }
        
        $this -> _localRedisOp = $this -> _allRedisOp[0];//默认第一组配置为本地IDC机房的redis
    }

    public function setOptions($options)
    {
        $this->_options = array_merge($this->_options, $options);
    }
    
    private function _connectLocalIDC($opcode)
    {
        $db = $this->_localRedisOp['host']['db'];
        $pwd = $this->_localRedisOp['host']['pwd'];
        $port = $this->_localRedisOp['host']['port'];
        $timeout = $this->_localRedisOp['host']['timeout'];

        if ($opcode == 'master') {
            if (!$this->_localRedisOp['wRedis']->IsConnected()) {
                $ip = $this->_localRedisOp['host']['master'];
                $this->_localRedisOp['wRedis']->pconnect($ip, $port, $timeout);
                if ($this->_options['auth'] && !$this->_localRedisOp['wRedis']->auth($pwd)) {
                    #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                }
                $this->_localRedisOp['wRedis']->select($db);
            }
        } else {
            if (!$this->_localRedisOp['rRedis']->IsConnected()) {
                $ip = $this->_localRedisOp['host']['slave'];
                $this->_localRedisOp['rRedis']->pconnect($ip, $port, $timeout);
                if($this->_options['auth'] && !$this->_localRedisOp['rRedis']->auth($pwd)) {
                    #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                }
                $this->_localRedisOp['rRedis']->select($db);
            }
        }
    }

    private function _connectRemoteIDC($opcode)
    {
        if($opcode == 'master') {
            foreach($this -> _allRedisOp as &$redis) {
                if(!$redis['wRedis']->IsConnected()) {
                    $ip      = $redis['host']['master'];
                    $port    = $redis['host']['port'];
                    $db      = $redis['host']['db'];
                    $pwd     = $redis['host']['pwd'];
                    $timeout = $redis['host']['timeout'];
                    $redis['wRedis']->pconnect($ip, $port, $timeout);
                    if($this->_options['auth'] && !$redis['wRedis']->auth($pwd)) {
                        #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                    }
                    $redis['wRedis'] -> select($db);
                }
            }
        } else {
            foreach ($this -> _allRedisOp as &$redis) {
                if(!$redis['rRedis']->IsConnected()) {
                    $ip  = $redis['host']['slave'];
                    $port= $redis['host']['port'];
                    $db  = $redis['host']['db'];
                    $pwd = $redis['host']['pwd'];
                    $timeout = $redis['host']['timeout'];
                    $redis['rRedis'] -> pconnect($ip, $port, $timeout);
                    if ($this->_options['auth'] && !$redis['rRedis']->auth($pwd)) {
                        #Logger::error("redis auth failed ip:$ip, port:$port, pwd:$pwd", 'redis/redis');
                    }
                    $redis['rRedis'] -> select($db);
                }
            }
        }
    }

    public function __call($method, $args)
    {
    	$errLog = array('method' => $method, 'args' => $args);
        #Logger::access($errLog, 'redis/redis');
        $logErrServers = array();
        $isWrite = in_array($method, $this->_redisWFuncs);

        if($this->_options['multi_idc'] && $isWrite) {
            $this->_connectRemoteIDC('master');
            $index = 0;
            foreach($this->_allRedisOp as $redis) {
                $result = call_user_func_array(array($redis['wRedis'],$method),$args);
                if (!$result) {
                    $logErrServers[] = $redis['host']['master'];
                }
                if ($index==0) {
                    $ret = $result;
                }
                $index++;
            }
        } else {
            $isWrite ? $this->_connectLocalIDC('master') : $this->_connectLocalIDC('slave');
            $isWrite ? $redis = $this->_localRedisOp['wRedis'] : $redis = $this->_localRedisOp['rRedis'];
            $ret = call_user_func_array(array($redis, $method), $args);
            if($isWrite && !$ret) {
                $logErrServers[] = $this->_localRedisOp['host']['master'];
            }
        }

        if ($isWrite && ($ret === false)) {
            $errLog['failsevers'] = $logErrServers;
            #Logger::error($errLog, 'redis/redis');
        }
        
        return $ret;
    }
}