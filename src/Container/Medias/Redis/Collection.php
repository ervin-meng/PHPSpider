<?php 
namespace PHPSpider\Container\Medias\Redis;

use PHPSpider\Component\Redis;

class Collection
{
    protected $_name;
    protected $_media;

    public function __construct($name,$media='redis',$config='')
    {
        $this->_name = $name;

        switch($media)
        {
            case 'redis':
                $this->_media = new Redis($config);
            break;
        }
    }

    public function get($pop=true)
    {
        if($pop)
        {
            return $this->_media->sPop($this->_name);
        }
        else{
            return $this->_media->sRandMember($this->_name);
        }
    }

    public function add($data)
    {
        return $this->_media->sAdd($this->_name,$data);
    }

    public function delete($data)
    {
        return $this->_media->sRemove($this->_name,$data);
    }

    public function isMember($data)
    {
        return $this->_media->sIsMember($this->_name,$data);
    }

    public function count()
    {
        return (int) $this->_media->sSize($this->_name);
    }

    public function clean()
    {
        return $this->_media->delete($this->_name);
    }
}