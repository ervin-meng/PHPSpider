<?php
namespace PHPSpider\Parsers\Adapters;

class CssSelector
{
    public static function translate($expression)
    {
        if(strpos($expression,',')) {
            $expressions = [];
            $expression  = explode(',',$expression);
            foreach($expression as $one) {
                $expressions[] = self::convert($one);
            }
            $expression = implode(' | ',$expressions);
        } else{
            $expression = self::convert($expression);
        }

        return $expression;
    }

    public static function convert($expression)
    {
        if ($expression == '*') { //*
            $expression = ".//*";
        } else if($expression[0]=='#') { //id
            $id = substr($expression,1);
            $expression = ".//*[@id='{$id}']";
        } else if($expression[0]=='.') { //class
            $class = substr($expression,1);
            $expression = ".//*[contains(concat(' ', normalize-space(@class), ' '),' ".$class." ')]";
        } else if(preg_match('/^[a-zA-Z]+$/',$expression)) { //element
            $expression = ".//{$expression}";
        }                                     //attribute

        return $expression;
    }
}