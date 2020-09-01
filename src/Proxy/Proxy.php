<?php
namespace PHPSpider\Proxy;

use GuzzleHttp\Client;
use PHPSpider\Container\Collection;
use PHPSpider\Utils\Config;

Class Proxy
{
    protected $_source;
    protected $_httpPool;
    protected $_httpsPool;
    protected $_client;

    protected $_verifyUrl = [
        'http' =>'http://blog.jobbole.com/all-posts/',
        'https'=>'https://tech.meituan.com/'
    ];

    public function __construct()
    {
        $this->_initPools();
    }

    protected function _initPools()
    {
        $this->_httpPool = new Collection('HttpProxy','redis',Config::get('redis'));
        $this->_httpsPool = new Collection('HttpsProxy','redis',Config::get('redis'));
    }

    public function get($https=false)
    {
        $pool = $https?$this->_httpsPool:$this->_httpPool;

        do
        {
            $ip = $pool->get(false);

        }while($ip && !$this->verify($ip,$https));

        if(!$https) //http 允许使用https
        {
            $ip = $this->get(true);
        }

        return $ip;
    }

    public function add($ip,$https=false)
    {
        $pool = $https?$this->_httpsPool:$this->_httpPool;

        return $pool->add($ip);
    }

    public function del($ip,$https=false)
    {
        $pool = $https?$this->_httpsPool:$this->_httpPool;

        return $pool->delete($ip);
    }

    public function verify($ip,$https=false,$inpool=true)
    {
        if(empty($this->_client))
        {
            $this->_client = new Client();
        }

        $verifyUrl = $https?$this->_verifyUrl['https']:$this->_verifyUrl['http'];

        try
        {
            $this->_client->request('get',$verifyUrl,['proxy'=>$ip,'timeout'=>5,'verify'=>false]);
            return true;
        }
        catch (\Exception $e)
        {
            if($inpool)
            {
                $result = $this->del($ip,$https);
            }

            return false;
        }
    }

    public function registerSourceHandler($source,$func='')
    {
        if(empty($func))
        {
            foreach ((array)$source as $class)
            {
                $classname = __NAMESPACE__.'\Source\\'.$class;
                $obj =  new $classname;

                $this->_source[$class] = [$obj,'handler'];
            }
        }
        else{
            $this->_source[$source] = $func;
        }
    }

    public function scanFromSource($source='',array $params = [],$process=false)
    {
        if(empty($source))
        {
            foreach($this->_source as $source=>$func)
            {
                $param = isset($params[$source])?$params[$source]:[];
                $this->scanFromSource($source,$param,$process);
            }
        }
        else if(isset($this->_source[$source]))
        {
            $ips = call_user_func_array($this->_source[$source],$params);

            if(empty($ips))
            {
                return false;
            }

            if($process)
            {
                $this->_initPools();
            }

            foreach($ips as $data)
            {
                if($this->verify($data['ip'],$data['https'],false))
                {
                    $this->add($data['ip'],$data['https']);
                }
            }
        }
        else
        {
            return false;
        }
    }

    public function scanFromIpSection($beginIp,$endIp,$ports=[80,8080])
    {
        $beginIp = ip2Long($beginIp);
        $endIp = ip2Long($endIp);

        for($i = $beginIp;$i<=$endIp;$i++)
        {
            foreach((array)$ports as $port)
            {
                $ip = long2ip($i).':'.$port;

                if($this->verify($ip,true,false))
                {
                    $this->add($ip,true);
                }
                else if($this->verify($ip))
                {
                    $this->add($ip);
                }
            }
        }
    }
}