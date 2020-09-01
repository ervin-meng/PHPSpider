<?php
namespace PHPSpider\Utils;

class Format
{
    static public function imgsrc($src,$benchmark)
    {
        /*
        $parsed = parse_url($benchmark);
        $siteurl = $parsed['scheme'].'://'.$parsed['host'].'/';
        $pattern = '/(<img)( [^>]*src=[\'|"])([^\'|"|http|data:image]+)([^>]*>)/';
        $content = preg_replace($pattern,'$1 width="600" $2'.$siteurl.'$3$4',$content);
        */
    }

    static public function url($urls,$benchmark)
    {
        $formaturls = [];

        $parsed = parse_url($benchmark);
        $siteurl = $parsed['scheme'].'://'.$parsed['host'];
        $path = dirname($parsed['path']);

        foreach ((array) $urls as $url)
        {
            if(empty($url))
            {
                continue;
            }

            if(!preg_match("/^(http|https):(\/\/|\\\\)(([\w\/\\\+\-~`@:%])+\.)+([\w\/\\\.\=\?\+\-~`@\':!%#]|(&)|&)+/i", $url))
            {
                if(substr($url,0,2) == '//')
                {
                    $url = $parsed['scheme'].':'.$url;
                }
                elseif (($url[0] == '/') || (substr($url,0,2) == './') || (substr($url,0,3) == '../'))
                {
                    $url = $siteurl.Format::path($url,$path);
                }
                else{
                    continue;
                }
            }

            $formaturls[] = $url;
        }

        return $formaturls;
    }

    static public function path($path,$cwd='',$separator='/')
    {
        $cwd = !empty($cwd)?:getcwd();

        if(substr($path,0,2)=='.'.$separator)
        {
            return $cwd.substr($path,1);
        }
        else if(substr($path,0,3)=='..'.$separator)
        {
            do
            {
                $path = substr($path,3);

                if(strlen($cwd) > 0)
                {
                    $cwd = dirname($cwd);
                }

            }while(substr($path,0,3) == '..'.$separator);

            return $cwd == DIRECTORY_SEPARATOR ? $separator.$path : $cwd.$separator.$path;
        }
        else{
            return $path;
        }
    }
}