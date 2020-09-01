<?php
namespace PHPSpider\Multiprocess;

use PHPSpider\Utils\Debug;
use PHPSPider\Utils\Logger;
use PHPSpider\Component\Redis;

class Process
{
    static protected $_STATUS = 0;  //0 初始值 1 开始 2 运行中 3 结束 4 暂停

    protected $_pid;
    protected $_ppid = 0;
    protected $_name;
    protected $_pidfile;
    protected $_stdoutfile;
    protected $_workers = [];
    protected $_commands = ['start','stop','restart','reload','help','pause','continue'];
    protected $_option = false;
    protected $_count = 0;
    protected $_jobs = [];
    protected $_alarm = 0;
    protected $_status_hashtable = null;

    public function __construct($name='Process',$pidfile='',$stdoutfile='')
    {
        $this->_name = $name;
        $this->_pidfile = !empty($pidfile)?:__DIR__."/".$name.".pid";
        $this->_stdoutfile = !empty($stdoutfile)?:__DIR__."/".$name."_stdout.log";
    }

    public function run($jobs,$count=1,$alarm=0)
    {
        if (PHP_SAPI != "cli")
        {
            exit("Only run in command line mode".PHP_EOL);
        }

        if($count<1)
        {
           exit('The number of worker processes must be greater than one'.PHP_EOL);
        }

        $this->_count = $count;
        $this->_jobs = $jobs;
        $this->_alarm = $alarm;

        global $argv;

        if(isset($argv[2]))
        {
            $this->_option = $argv[2];
        }

        if(isset($argv[1]) && in_array($argv[1],$this->_commands))
        {
            call_user_func_array(array($this,'_'.$argv[1]),[]);
        }
        else{
            $this->_help();
        }
    }

    protected function _start()
    {
        if(file_exists($this->_pidfile))
        {
            exit("Process {$this->_name} is Running".PHP_EOL);
        }

        if($this->_option == '-d')
        {
            $this->_daemonize();
        }

        $this->log("master process [PID:".posix_getpid()."] started");
        $this->_setStatus(1);
        $this->_registerSignal();
        $this->_workerStart();
        $this->_createMasterPidFile();
        $this->_listen();
    }

    protected function _workerStart()
    {
        Hook::invoke('onStart');

        if($this->_alarm)
        {
            pcntl_alarm($this->_alarm);
        }

        foreach( (array) $this->_jobs as $job)
        {
            $this->_forkWorkers($job,$this->_count);
        }
    }

    protected function _workerStop()
    {
        Hook::invoke('onStop');
    }

    protected function _stop()
    {
        if(!file_exists($this->_pidfile))
        {
            exit("The process {$this->_name} is not Running".PHP_EOL);
        }

        posix_kill($this->getMasterPid(),SIGINT);
    }

    protected function _restart() //待测
    {
        if(!file_exists($this->_pidfile))
        {
            exit("The process {$this->_name} is not Running".PHP_EOL);
        }

        $this->_stop();

        echo "The process {$this->_name} is restarting".PHP_EOL;

        while(file_exists($this->pidfile))
        {
            sleep(1);
        }

        $this->_start();
    }

    protected function _reload()  //待测
    {
        if(!file_exists($this->_pidfile))
        {
            exit("The process {$this->_name} is not Running".PHP_EOL);
        }

        posix_kill($this->getMasterPid(),SIGUSR1);

        $this->_start();
    }

    protected function _pause()
    {
        posix_kill($this->getMasterPid(),SIGUSR2);
    }

    protected function _continue()
    {
        posix_kill($this->getMasterPid(),SIGCONT);
        posix_kill($this->getMasterPid(),SIGUSR2);
    }

    protected function _help()
    {
        $msg=<<<'EOF'
USAGE
  yourfile.php command [options]
COMMANDS
  help         Show this help.
  restart      Stop all running process, then Start.
  start        Start process.
  stop         Stop all running process.
  reload       Gracefully restart daemon processes in-place to pick up changes to
               source. This will not disrupt running workers. most publishing should use yourfile.php reload
OPTIONS
  -d           Start or Reload in daemon 
EOF;
        echo $msg.PHP_EOL;
    }

    protected function _registerSignal()
    {
        pcntl_signal(SIGUSR1, array($this, 'signalHandler'), false); //reload
        pcntl_signal(SIGUSR2, array($this, 'signalHandler'), false); //pause && continue
        pcntl_signal(SIGINT, array($this, 'signalHandler'), false);  //stop
        pcntl_signal(SIGALRM,array($this, 'signalHandler'),false);   //alarm
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    public function signalHandler($signal)
    {
        switch ($signal)
        {
            case SIGUSR2:  //SIGSTOP SIGCONT

                if(self::$_STATUS == 4)
                {
                    $this->_setStatus(2);
                    $signal = SIGCONT;
                }
                else{
                    $this->_setStatus(4);
                    $signal = SIGSTOP;
                }

                foreach($this->_workers as $chldpid => $job)
                {
                    posix_kill($chldpid,$signal);
                }

                posix_kill($this->getMasterPid(),$signal);

            break;

            case SIGUSR1: //reload
                Hook::invoke('onReload');
                @unlink($this->_pidfile);
                exit();
            break;

            case SIGINT: //stop
                $this->_setStatus(3);

                foreach ($this->_workers as $pid=>$job)
                {
                    posix_kill($pid,SIGKILL);
                    $this->log("worker process [PID:{$pid}] stoped");
                    unset($this->_workers[$pid]);
                }

                @unlink($this->_pidfile);
                $this->_workerStop();
                $this->log("master process [PID:{$this->_pid}] stoped");
                exit();
            break;

            case SIGALRM: //alarm
                $this->_workerStart();
            break;
        }
    }

    protected  function _daemonize()
    {
        umask(0);

        $pid = pcntl_fork();

        if (-1 === $pid)
        {
            exit('daemonize fork fail');
        }
        elseif ($pid > 0) {
            exit(0);
        }

        if (-1 === posix_setsid())
        {
            exit("daemonize setsid fail");
        }

        $pid = pcntl_fork();

        if (-1 === $pid)
        {
            exit("daemonize fork fail again");
        }
        elseif (0 !== $pid)
        {
            exit(0);
        }

        if ($handle = fopen($this->_stdoutfile, "a"))
        {
            global $STDOUT, $STDERR;
            unset($handle);
            @fclose(STDOUT);
            @fclose(STDERR);
            $STDOUT = fopen($this->_stdoutfile,"a");
            $STDERR = fopen($this->_stdoutfile,"a");
        }
        else {
            exit('daemonize can not open stdoutFile ' .$this->_stdoutfile);
        }
    }

    protected function _forkWorkers($job,$count=1)
    {
        while($count>=1)
        {
            $pid = pcntl_fork();
            $this->_pid = posix_getpid();

            sleep(1);

            switch($pid)
            {
                case '-1':
                    exit('can not fork child process ');
                break;

                case '0';
                    $this->_setProcessTitle("{$this->_name}: worker process.");
                    $this->log("worker process [PID:{$this->_pid}] started");
                    $this->_ppid = posix_getppid();
                    call_user_func_array($job,[]);
                    exit();
                break;

                default:
                    $this->_setProcessTitle("{$this->_name}: master process. ".$this->getRunFileName());
                    $this->_workers[$pid] = $job;
                break;
            }

            $count--;
        }
    }

    protected function _listen()
    {
        $this->log("master process [PID:{$this->_pid}] listening");
        $this->_setStatus(2);

        while (1)
        {
            pcntl_signal_dispatch();
            $pid = pcntl_wait($status);

            if($pid>0 && isset($this->_workers[$pid]))
            {
                $this->log("worker process [PID:{$pid}] stoped with [STATUS:{$status}]");

                $job = $this->_workers[$pid];
                unset($this->_workers[$pid]);

                if(self::$_STATUS < 3 && $status!=0)
                {
                    $this->_forkWorkers($job);
                }

                if(empty($this->_workers))
                {
                    if($this->_alarm)
                    {
                        $this->_workerStop();
                    }
                    else
                    {
                        $this->_stop();
                    }

                }
            }
        }
    }

    public function log($msg,$file='')
    {
        echo date('Y-m-d H:i:s').' '."[NAME:{$this->_name}] ".$msg.PHP_EOL;
    }

    public function getPid()
    {
        return $this->_pid;
    }

    public function getMasterPid()
    {
        if(file_exists($this->_pidfile))
        {
            return file_get_contents($this->_pidfile);
        }

        return false;
    }

    public function getRunFileName()
    {
        return getcwd().'/'.pathinfo($_SERVER['SCRIPT_FILENAME'],PATHINFO_BASENAME);
    }

    protected function _createMasterPidFile()
    {
        return file_put_contents($this->_pidfile,$this->_pid);
    }

    protected function _setProcessTitle($title)
    {
        cli_set_process_title($title);
    }

    protected function _setStatus($status)
    {
        self::$_STATUS = $status;
    }
}
