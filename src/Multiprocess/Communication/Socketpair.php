<?php
    namespace PHPSpider\Queues;
    
    use PHPSpider\Exception\SpiderException;
    
    /**
     * 设置非阻塞方式可以避免读阻塞
     */
    class Socketpair 
    {
        protected $_fd;

        public function __construct()
        {
            $this->_fd = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            stream_set_blocking($this->_fd[0],0);
        }

        public function write($data)
        {
            fwrite($this->_fd[1],$data);
        }

        public function read($queue)
        {
            fgets($this->_fd[0]);
        }

        public function close()
        {
            fclose($this->_fd);
        }
    }