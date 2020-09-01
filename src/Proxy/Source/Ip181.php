<?php
namespace PHPSpider\Proxy\Source;

use GuzzleHttp\Client;
use PHPSpider\Parsers\HtmlParser;

class Ip181
{
    public function handler($html='')
    {
        $ips = [];

        try
        {
            if(empty($html))
            {
                $url = 'http://www.ip181.com/';
                $method = 'get';
                $options = [];
                $client = new Client();
                $html = $client->request($method,$url,$options)->getBody()->getContents();
            }

            $parser = HtmlParser::load($html);
            $nodeList = $parser->find('tr');

            unset($nodeList[0]);

            foreach($nodeList as $node)
            {
                $arr = explode(PHP_EOL,$node->text());

                $ip = trim($arr[0]).':'.trim($arr[1]);
                $https = stristr($arr[3],'s')?true:false;
                $level = trim($arr[2]);
                $encoding = mb_detect_encoding($level);

                if($encoding!='UTF-8')
                {
                    $level = iconv($encoding,'UTF-8',$level);
                }

                if($level=='高匿')
                {
                    $ips [] = ['ip'=>$ip,'https'=>$https];
                }
            }
        }catch(\Exception $e){}

        return $ips;
    }
}