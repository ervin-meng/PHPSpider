<?php    
/******************************************************************************************************
 * @Brief 日志通用类 (在项目程序入口处初始化日志类)
 * $logPath = '/data/log/';
 ****************************************************************
 * Logger::init();//初始化  未指定目录则默认日志根目录为LOG_PATH
 * Logger::init($logPath);//初始化 并设定日志目录
 ****************************************************
 * $logMsg = array('a'=>123,'b'=>array('c'=>345,'d'=>567));
 * //目录分隔符用'/'，按'/'分割后得到的末尾字符串为文件名;文件名可由‘数字/英文字母/下划线’组成；
 * $dirFileName = "trade/net_connet";
 * Logger::access($logMsg,$dirFileName); //写流水日志
 * Logger::error($logMsg,$dirFileName);  //写错误日志
 * Logger::except($logMsg,$dirFileName); //写异常日志
 *********************最终生成日志文件路径形如：***************************
 * '/data/log/trade/net_connet_access.20151020.txt' //日期部分按实际生成日期
 * '/data/log/trade/net_connet_error.20151020.txt'  //日期部分按实际生成日期
 * '/data/log/trade/net_connet_except.20151020.txt' //日期部分按实际生成日期
 *****************************************************************************************************/
namespace PHPSpider\Utils;

use PHPSpider\Protocols\Http;

class Logger
{
    public    $logPath;	         //日志根目录

    protected $errInFile;        //错误所在文件
    protected $errInLine;        //错误所在行
    protected $intStartTime;     //初始时间
    protected $intLogId;		 //日志id,
    protected $logFileTag = "";	 //文件名中包含的字符标记
    protected $logFileExt = "";	 //文件后缀名
    protected $linePreTag = "";  //日志行前缀（行头字符串）
    protected $lineSufTag = "\n"; //日志行后缀（行尾字符串）
    protected $ignoreItems = array(''); //不需要默认记录的日志内容项 如time、ip、uri
    protected $items = ['time','logId','line','rip','uri'];

    private static $instance = null;

    public static function init($logRootPath='',$logFileExt='log')
    {
        if( self::$instance !== null )
        {
            return false;
        }

        if(!defined('PROCESS_START_TIME'))
        {
            define('PROCESS_START_TIME', microtime(true) * 1000000);
        }

        if(!$logRootPath)
        {
            $logRootPath = LOG_PATH;
        }

        Logger::getInstance()->logPath = rtrim($logRootPath,'/');
        Logger::getInstance()->logFileExt = $logFileExt;
    }

    public static function getInstance()
    {
        if( self::$instance === null )
        {
            if(defined('PROCESS_START_TIME'))
            {
                $intStartTime = PROCESS_START_TIME;
            }
            elseif(isset($_SERVER['REQUEST_TIME']))
            {
                $intStartTime = $_SERVER['REQUEST_TIME'] * 100000;
            }
            else
            {
                $intStartTime = microtime(true)*1000000;

            }

            self::$instance = new Logger($intStartTime);
        }

        return self::$instance;
    }

    public  function __construct($intStartTime)
    {
        $this->intStartTime = $intStartTime;
        $this->intLogId     = $this->_logId();
    }

    public static function access($msg,$name,$dateFormat='d',$ignoreItems=array(),$whiteSpace=false)
    {
        Logger::getInstance()->logFileTag = '_access';
        return Logger::getInstance()->_write($msg, $name,$dateFormat,$ignoreItems,$whiteSpace);
    }

    public static function error($msg, $name,$dateFormat='d',$ignoreItems=array(),$whiteSpace=false)
    {
        Logger::getInstance()->logFileTag = '_error';
        return Logger::getInstance()->_write($msg, $name,$dateFormat,$ignoreItems,$whiteSpace);
    }

    public static function except($msg,$name,$dateFormat='d',$ignoreItems=array(),$whiteSpace=false)
    {
        Logger::getInstance()->logFileTag = '_except';
        return Logger::getInstance()->_write($msg, $name,$dateFormat,$ignoreItems,$whiteSpace);
    }

    public static function write($msg,$name,$dateFormat='d',$ignoreItems=array(),$whiteSpace=false)
    {
        Logger::getInstance()->logFileTag = '';
        return Logger::getInstance()->_write($msg, $name,$dateFormat,$ignoreItems,$whiteSpace);
    }

    /**
     * 写日志（通用）
     *
     * @param mix $msg (字符串|布尔值|数组|对象)
     * @param string $name 日志路径及文件名
     * @param string $dateFormat 日志文件切分规则 (值为 y:按年切分 m:按月切分 d:按日切分 h:按小时切分 )
     * @param array $ignoreItems 不需要记录的默认日志内容项(值如：array('uri','time'))
     * @return bool
     */
    private function _write($msg,$name,$dateFormat='d',$ignoreItems=array(),$whiteSpace=false)
    {
        try
        {
            $trace = debug_backtrace();

            if($this->errInFile && $this->errInLine)
            {
                $file = $this->errInFile;
                $line = $this->errInLine;
            }
            else{ //0是避免匿名函数调用
                $file = isset($trace[1]['file'])?$trace[1]['file']:$trace[0]['file'];
                $line = isset($trace[1]['line'])?$trace[1]['line']:$trace[0]['line'];
            }

            $name = rtrim(trim($name),'/');

             //绝对路径
            if((strpos(PHP_OS,'WIN')===false && $name[0] =='/') || (strpos(PHP_OS,'WIN')!==false && strpos($name,':')))
            {
                $logDir = dirname($name);
            }
            else if(substr($name,0,2)=='./' || substr($name,0,3)=='../') //相对于当前工作目录
            {
                $logDir = dirname($name);
            }
            else if(!empty($this->logPath)) //相对logpath路径
            {
                $logDir = dirname($this->logPath.'/'.$name);
            }
            else //相对于运行脚本路径
            {
                $logDir = dirname(dirname($file).'/'.$name);
            }

            if(!is_dir($logDir))
            {
                @mkdir($logDir,0777,true);
            }

            $dateFormatStr = $this->_getDateFormatStr($dateFormat);

            $logFileExt = pathinfo($name,PATHINFO_EXTENSION)?:$this->logFileExt;
            $logFileName = pathinfo($name,PATHINFO_FILENAME);
            $logFile = $logDir.'/'.$logFileName.$this->logFileTag.$dateFormatStr.".".$logFileExt;

            $arrLog = [
                'time'  => date('Y-m-d H:i:s'),
                'logId' => $this->intLogId,
                'line'  => basename($file) . ':' . $line,
                'rip'   => Http::getClientIp(),
                'uri'   => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            ];

            if(is_array($ignoreItems) && is_array($this->ignoreItems))
            {
                $ignoreItems = array_merge($ignoreItems,$this->ignoreItems);
            }

            if(is_array($ignoreItems))
            {
                foreach($ignoreItems as $term)
                {
                    if(isset($arrLog[$term]))
                    {
                        unset($arrLog[$term]);
                    }
                }
            }

            $logStr = '';

            foreach($arrLog as $k => $v)
            {
                $logStr .= $k . '[' . $v . '] ';
            }

            if(is_string($msg))
            {
                $logStr .= $msg;
            }
            else if(is_bool($msg))
            {
                $msg = ($msg) ? 'true' : 'false';
                $logStr .= $msg;
            }
            else if(is_array($msg))
            {
                $logStr .= $this->_arrToStr($msg);
            }
            else
            {
                $logStr .= serialize($msg);
            }

            if(!$whiteSpace)
            {
                $logStr = preg_replace('/[\0\f\n\r\t\v]+/','',$logStr);
            }

            $logLine  = $this->linePreTag.$logStr.$this->lineSufTag;

            error_log($logLine, 3, $logFile);
            @chmod($logFile, 0777);
            return true;
        }
        catch(Exception $e){
        }
    }

    public static function getLogId()
    {
        return Logger::getInstance()->intLogId;
    }

    public static function setLogId($logId)
    {
       Logger::getInstance()->intLogId = $logId;
    }

    public static function setErrFileLine($errInFile='',$errInLine='')
    {
        if($errInFile && $errInLine)
        {
            Logger::getInstance()->errInFile = $errInFile;
            Logger::getInstance()->errInLine = $errInLine;
        }
    }

    public static function setIgnoreItems($arrItems=array())
    {
        Logger::getInstance()->ignoreItems = $arrItems;
    }

    private function _arrToStr($arr, $needSerialize = false)
    {
        $str = '';
        $i = 0;
        if(is_array($arr))
        {
            foreach($arr as $k=>$v)
            {
                $str_k = ($k !== $i) ? ($k . '=') : '';
                if(is_string($v) || is_numeric($v) || is_bool($v) || is_null($v))
                {
                    $str .= $str_k . $v . ',';
                }
                else
                {
                    if($needSerialize)
                    {
                        $str .= $str_k . serialize($v) . ',';
                    }
                    else
                    {
                        $str .= $str_k . '<@' . $this->_arrToStr($v, true) . '@>,';
                    }
                }
                $i++;
            }
        }
        else
        {
            $str = serialize($arr);
        }

        return rtrim($str, ',');
    }

    /**
     * 构造logid(优先使用上游调用传递过来的logid)
     *
     * @return mix (预期统一规范为数字字符串)
     */
    private static function _logId()
    {
        $inputs = $_POST + $_GET;

        if(isset($inputs['_logId_']))
        {
            return $inputs['_logId_'];
        }
        else
        {
            $arr = gettimeofday();
            return ((($arr['sec']*100000 + $arr['usec']/10) & 0x7FFFFFFF) | 0x80000000);
        }
    }

    private function _getDateFormatStr($dateFormat)
    {
        switch ($dateFormat)
        {
            case 'y':
                $dateFormatStr = Date('Y');
            break;
            case 'm':
                $dateFormatStr = Date('Ym');
            break;
            case 'd':
                $dateFormatStr = Date('Ymd');
            break;
            case 'h':
                $dateFormatStr = Date('YmdH');
            break;
            default:
                $dateFormatStr = '';

        }

        return empty($dateFormatStr)?'':'_'.$dateFormatStr;
    }
}
?>