<?php
namespace PHPSpider\Utils;

class Memory
{
    static public $_units = ['B', 'KB', 'MB', 'GB', 'TB','PB'];

    static public function getLimit()
    {
        return ini_get('memory_limit');
    }

    static public function getUsed($unit = 'B',$dec=2)
    {
        $memoryUsed = memory_get_usage();

        return self::unitConversion($memoryUsed,'B',$unit,$dec);
    }

    static public function getPeakUsed($unit = 'B',$dec=2)
    {
        $memoryPeakUsed = memory_get_peak_usage();

        return self::unitConversion($memoryPeakUsed,'B',$unit,$dec);
    }

    static public function unitConversion($value,$from,$to,$dec=2)
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if($from == $to)
        {
            return $value;
        }

        $fromIndex = array_search($from,self::$_units);
        $toIndex = array_search($to,self::$_units);

        if($fromIndex===false || $toIndex ===false || $fromIndex > $toIndex)
        {
            throw new \Exception('unit can not conversion');
        }

        while ($value >= 1024 && $fromIndex<$toIndex)
        {
            $value /= 1024;
            $fromIndex++;
        }

        return round($value,$dec) . " " . self::$_units[$toIndex];
    }
}