<?php 
namespace PHPSpider\Container;

use PHPSpider\Component\Redis;
use PHPSpider\Utils\Config;

class LineList
{
    const TYPE_QUEUE = 1;
    const TYPE_STACK = 2;

    protected $_type;
    protected $_name;
    protected $_media;

    public function __construct($name='', $media='redis', $config='', $type = self::TYPE_QUEUE)
    {
        $this->_name = $name;
        $this->_type = $type;

        if(empty($config)) {
            $config = Config::get($media);
        }

        switch ($media)
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
        if ($this->_type == self::TYPE_QUEUE) {
            return  $this->_media->lPop($this->_name);
        } else {
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