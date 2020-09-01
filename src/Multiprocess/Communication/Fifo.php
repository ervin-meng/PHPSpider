<?php
namespace PHPSpider\Queues;

use PHPSpider\Exception\SpiderException;

/**
 * 如果管道另一端没有调用写 会造成读阻塞 设置非阻塞也不好使 stream_set_blocking($fd,0)
 * 内容只能读取一次,读一次就会在管道消失
 * @example
 *  $pipePath = "/tmp/test.pipe";
    if( !file_exists( $pipePath ) ){
        if( !posix_mkfifo( $pipePath, 0666 ) ){
            exit('make pipe false!' . PHP_EOL);
        }
    }

    $pid = pcntl_fork();

    if($pid==0){
        $w = fopen($pipePath,'w'); //如果管道另一端没有调用写 会造成读阻塞
        $r = fopen($pipePath,'r');
    }else{

        $r = fopen($pipePath,'r');
        stream_set_blocking($r,0);
        echo fgets($r);
        $w = fopen($pipePath,'w');
        fwrite($w,'hello world');
    }
 */

class Fifo
{
    private $_path;
    private $_w;
    private $_r;

    public function __construct($name = 'fifo', $mode = 0666)
    {
        $this->_path = "/tmp/{$name}.".posix_getpid().'.pipe';

        if (!file_exists($this->_path))
        {
            if (!posix_mkfifo($this->_path, $mode))
            {
                throw new SpiderException('can not create pipe '.$name);
            }
        }
    }

    public function write($data)
    {
        return fwrite($this->getW(),$data);
    }

    public function read()
    {
        return fgets($this->getR());
    }

    public function close()
    {
        fclose($this->_r);
        fclose($this->_w);
        unlink($this->_path);
    }

    public function getW()
    {
        if(!is_resource($this->_w))
        {
            $this->_w = fopen($this->_path, 'w');

            if ($this->_w == NULL)
            {
                #stream_set_blocking($this->_w,false);
                throw new SpiderException("open pipe {$this->_path} for write error");
            }
        }

        return $this->_w;
    }

    public function getR()
    {
        if(!is_resource($this->_r))
        {
            $this->_r = fopen($this->_path, 'r');

            if ($this->_r == NULL)
            {
                #stream_set_blocking($this->_r,false);
                throw new SpiderException("open pipe {$this->_path} for read error");
            }
        }

        return $this->_r;
    }
}