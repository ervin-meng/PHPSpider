<?php
namespace PHPSpider\Utils;

use PHPSpider\Utils\Memory;
use PHPSpider\Utils\Time;

class Debug
{
    protected static $_points = [];

    static public function begin($point)
    {
        self::setPoint($point);
    }

    static public function end($point,&$info = [])
    {
        return self::info($point,'',$info);
    }

    static public function setPoint($point)
    {
        self::$_points[$point]['memory'] = Memory::getUsed();
        self::$_points[$point]['time'] = microtime(true)*1000000;
        self::$_points[$point]['startime'] = time();
    }

    static public function getPoint($point)
    {
        return isset(self::$_points['$point'])?self::$_points[$point]:[];
    }

    static public function info($startPoint,$endPoint='',&$info=[])
    {
        $start_memory = empty($startPoint)?0:self::$_points[$startPoint]['memory'];
        $end_memory = empty($endPoint)? Memory::getUsed():self::$_points[$endPoint]['memory'];
        $start_time = empty($startPoint)?0:self::$_points[$startPoint]['time'];
        $end_time = empty($endPoint)? microtime(true)*1000000:self::$_points[$endPoint]['time'];

        $info['memoryused'] = Memory::unitConversion($end_memory-$start_memory,'B','KB');
        $info['timeused']   = Time::toSecond($end_time-$start_time);
        $info['starttime']  = date('Y-m-d H:i:s',self::$_points[$startPoint]['startime']);
        $info['endtime']    = date('Y-m-d H:i:s',time());

        return $info;
    }

    static public function backtrace()
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    static public function printBacktrace()
    {
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
    }

    static public function zvalDump($val)
    {
        debug_zval_dump($val);
    }

    static public function valExport($expression,$return = false)
    {
        var_export($expression,$return);
    }

    static public function tokenDump($source)
    {
        return token_get_all($source);
    }

    static public function getTokenName($token)
    {
        return token_name($token);
    }
}