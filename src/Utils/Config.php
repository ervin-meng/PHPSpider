<?php
namespace PHPSpider\Utils;

class Config
{
    static protected $_config = [];

    static public function get($field)
    {

        $value = Null;

        if(file_exists(__DIR__."/../Config.php") && empty(self::$_config))
        {
            self::$_config = include(__DIR__.'/../Config.php');
        }

        $fields = explode('.',$field);

        foreach($fields as $key)
        {
            if(isset($value[$key]))
            {
                $value = $value[$key];
            }
            else if(isset(self::$_config[$key]))
            {
                $value = self::$_config[$key];
            }
            else {
                break;
            }
        }

        return $value;
    }
}