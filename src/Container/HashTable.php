<?php 
namespace PHPSpider\Container;

use PHPSpider\Component\Redis;

class HashTable
{
    protected $_name;
    protected $_media;

    public function __construct($name='',$media='redis',$config='')
    {
        $this->_name = $name;

        switch($media)
        {
            case 'redis':
                $this->_media = new Redis($config);
            break;
        }
    }

    public function get($key)
    {

    }

    public function set($key,$value)
    {

    }

    public function exists($key)
    {

    }

    public function del($key)
    {

    }

    public function keys()
    {

    }

    public function values()
    {

    }

    public function len()
    {
        return $this->_media->lLen($this->_name);
    }

    public function clean()
    {
        return $this->_media->delete($this->_name);
    }
}