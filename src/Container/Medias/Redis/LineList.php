<?php
namespace PHPSpider\Container\Medias\Redis;

use PHPSpider\Component\Redis;

class LineList
{
    protected $_type = 'stack'; //queue
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

    public function add($data)
    {
        return $this->_media->rPush($this->_name,$data);
    }

    public function next()
    {
        if($this->_type=='queue')
        {
            return  $this->_media->lPop($this->_name);
        }
        else{
            return  $this->_media->rPop($this->_name);
        }
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