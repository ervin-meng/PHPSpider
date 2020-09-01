<?php
namespace PHPSpider\Utils;

class Time
{
    static public $_units = ['μs'=>1000,'ms'=>1000,'s'=>1000,'min'=>'60','h'=>'60','d'=>'24'];

    //将秒数转换为时间（年、天、小时、分、秒
    static public function SecondToDate($time)
    {
        $res = '';

        if(is_numeric($time))
        {
    $value = array();

    if($time >= 31556926)
            {
                $value["years"] = floor($time/31556926);
                $time = ($time%31556926);

                if($value["years"])
                {
                    $res.= $value["years"] ."年";
                }
    }

    if($time > 86400)
            {
                $value["days"] = floor($time/86400);
                $time = ($time%86400);

                if($value["days"])
                {
                    $res.= $value["days"] ."天";
                }
    }

    if($time >= 3600)
            {
                $value["hours"] = floor($time/3600);
                $time = ($time%3600);

                if($value["hours"])
                {
                    $res.= $value["hours"] ."小时";
                }
    }

    if($time > 60)
            {
                $value["minutes"] = floor($time/60);
                $time = ($time%60);

                if($value["minutes"])
                {
                    $res.= $value["minutes"] ."分";
                }
    }

    $time = floor($time);

    if($time)
            {
                $value["seconds"] = $time;
                $res.= $time ."秒";
    }
        }

        if(count($value)==1 && isset($value["minutes"]))
        {
            $res.= "钟";
        }

        return $res;
    }

    static public function toSecond($time)
    {
        $units = ['μs','ms','s'];

        $pos  = 0;

        while ($time >= 1000 && $pos<2)
        {
            $time /= 1000;
            $pos++;
        }

        return round($time,6) . " " . $units[$pos];
    }
}