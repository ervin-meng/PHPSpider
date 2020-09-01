<?php

namespace PHPSpider\Component\Mysql;

use PDO;
use PHPSpider\Utils\Debug;
use PHPSPider\Utils\Logger;

class Connector
{
    protected $_config;
    protected $_linkWrite;
    protected $_linkRead;
    protected $_links = [];

    //PDO连接参数
    protected $_params = [
        PDO::ATTR_CASE              => PDO::CASE_NATURAL,
        PDO::ATTR_ERRMODE           => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_ORACLE_NULLS      => PDO::NULL_NATURAL,
        PDO::ATTR_STRINGIFY_FETCHES => false,
        PDO::ATTR_EMULATE_PREPARES  => false,
    ];
       
    public function __construct(array $config) 
    {
        $this->_config = $config;
    }

    /**
     * @param  array $config
     * @param  int   $linkNo
     * @param  bool  $autoConnection
     * @return mixed
     */
    protected function _connect(array $config = [], $linkNo = 0, $autoConnection = false)
    {
        if (!isset($this->_links[$linkNo])) {
            $config = !empty($config)?array_merge($this->_config,$config):$this->_config;
            if (isset($config['params']) && is_array($config['params'])) {
                $params = $config['params'] + $this->_params;
            } else {
                $params = $this->_params;
            }
            
            if (empty($config['dsn'])) {
                $config['dsn'] = self::getDsn($config);
            }
            
            try {
                $this->debug(true);
                $this->_links[$linkNo] = new PDO($config['dsn'], $config['username'], $config['password'], $params);
                $this->debug(false,$config['dsn']);
            } catch (\PDOException $e) {
                $this->log('[PDOEXCEPTION:'.$e->getMessage().']');
                if ($autoConnection) {
                    return $this->_connect($autoConnection, $linkNo);
                } else {
                    throw $e;
                }
            }
        }
        
        return $this->_links[$linkNo];
    }
    
    protected function _multiConnect($master = false)
    {
        $_config = [];

        foreach (['username', 'password', 'host', 'port', 'dbname', 'dsn', 'charset'] as $name) {
            $_config[$name] = explode(',',$this->_config[$name]);
        }

        $m = floor(mt_rand(0, $this->_config['master_num'] - 1));

        if ($this->_config['rw_separate']) {
            if ($master) {
                $r = $m;
            } elseif (is_numeric($this->_config['slave_no'])) {
                $r = $this->_config['slave_no'];
            } else {
                $r = floor(mt_rand($this->_config['master_num'], count($_config['host']) - 1));
            }
        } else {
            $r = floor(mt_rand(0, count($_config['host']) - 1));
        }
        
        $dbMaster = false;
        
        if ($m != $r) {
            $dbMaster = [];
            foreach (['username', 'password', 'host', 'port', 'dbname', 'dsn', 'charset'] as $name) {
                $dbMaster[$name] = isset($_config[$name][$m]) ? $_config[$name][$m] : $_config[$name][0];
            }
        }

        $dbConfig = [];
        
        foreach (['username', 'password', 'host', 'port', 'dbname', 'dsn', 'charset'] as $name) {
            $dbConfig[$name] = isset($_config[$name][$r]) ? $_config[$name][$r] : $_config[$name][0];
        }
        
        return $this->_connect($dbConfig, $r, $r == $m ? false : $dbMaster);
    }
    
    public function getLink($master = true)
    {
        if (!empty($this->_config['deploy'])) {
            if ($master) {
                if (!$this->_linkWrite) {
                    $this->_linkWrite = $this->_multiConnect(true);
                }
                return $this->_linkWrite;
            } else {
                if (!$this->_linkRead) {
                    $this->_linkRead = $this-_multiConnect(false);
                }
                return $this->_linkRead;
            }
        } else {
            return $this->_connect();
        }
    }
   
    public function isBreak($e)
    {
        if (false !== stripos($e->getMessage(), 'server has gone away')) {
            return true;
        }
        
        return false;
    }
    
    public function close()
    {
        $this->_linkWrite = null;
        $this->_linkRead  = null;
        $this->_links     = [];
        
        return $this;
    }
    
    public static function getDsn($config)
    {
        $dsn = 'mysql:dbname=' . $config['dbname'] . ';host=' . $config['host'];
        
        if (!empty($config['port'])) {
            $dsn .= ';port=' . $config['port'];
        } elseif (!empty($config['socket'])) {
            $dsn .= ';unix_socket=' . $config['socket'];
        }
        
        if (!empty($config['charset'])) {
            $dsn .= ';charset=' . $config['charset'];
        }
       
        return $dsn;
    }
    
    protected function debug($start, $dsn='')
    {
        if (empty($this->_config['debug'])) {
            return false;
        }
        
        if ($start) {
            Debug::begin('__CONNECT__');
        } else {
            $info = Debug::end('__CONNECT__');
            $msg = "[TIMEUSED:{$info['timeused']}] [MEMORYUSED:{$info['memoryused']}] [DSN:{$dsn}]";
            $this->log($msg);
        }
    }
    
    protected function log($msg)
    {
        $ignoreItems = ['logId','rip','uri'];
        Logger::write($msg,$this->_config['logfile'],'d',$ignoreItems);
    }
}
