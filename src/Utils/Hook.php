<?php
namespace PHPSpider\Utils;

Class Hook
{
    protected static $_events = [];

    static public function register($event,$action='',$params=[],$mode=NULL)
    {
        isset(self::$_events[$event]) || self::$_events[$event] = [];

        if($action)
        {
            if($mode == 'reset')
            {
                unset(self::$_events[$event]);
            }

            self::$_events[$event][] = [$action,$params];
        }
    }

    static public function invoke($event)
    {
        $hooks = isset(self::$_events[$event])?self::$_events[$event]:[];

        if(!empty($hooks))
        {
            foreach($hooks as $callback)
            {
                call_user_func_array($callback[0],$callback[1]);
            }
        }
    }
}