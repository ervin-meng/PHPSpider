<?php
namespace PHPSpider\Proxy\Source;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;

use PHPSpider\Protocols\Http;
use PHPSpider\Parsers\HtmlParser;

class Goubanjia
{
    public function handler($html='')
    {
        $ips = [];

        try
        {
            if(empty($html))
            {
                $client = new Client();

                $method = 'get';
                $url = 'http://www.goubanjia.com/';

                $headers  = [
                    'Connection'=>'keep-alive',
                    'Cache-Control'=>'max-age=0',
                    'Upgrade-Insecure-Requests'=> '1',
                    'User-Agent'=>Http::builderUserAgent(),
                    'Accept'=>'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Encoding'=>'gzip, deflate, sdch',
                    'Accept-Language'=>'zh-CN,zh;q=0.8'
                ];

                $cookiearr = [
                    'JSESSIONID'=>'8EABA6AB2EC6C3FF7D24268ACC0A3988',
                    'UM_distinctid'=>'15f8a46873c29f-0a0a76a3b93884-6a11157a-100200-15f8a46873e5c8',
                    'CNZZDATA1253707717'=>'954931427-1509849326-%7C1509849326',
                    'Hm_lvt_2e4ebee39b2c69a3920a396b87bbb8cc'=>'1509850452',
                    'Hm_lpvt_2e4ebee39b2c69a3920a396b87bbb8cc'=>'1509853399'
                ];

                $cookie = CookieJar::fromArray($cookiearr,'.goubanjia.com');

                $options = [
                    'headers'=>$headers,
                    'cookies'=>$cookie,
                ];

                $html = $client->request($method,$url,$options)->getBody()->getContents();
            }

            $parser = HtmlParser::load($html);
            $nodeList = $parser->find('tr');

            unset($nodeList[0]);

            foreach($nodeList as $node)
            {
                $arr = explode(PHP_EOL,$node->text());

                if(!is_array($arr) || count($arr)<3)
                {
                    continue;
                }

                $level = trim($arr[1]);
                $ip = trim($arr[0]);
                $https = stristr($arr[2],'s')?true:false;

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
        }
        catch (\Exception $e){

        }

        return $ips;
    }
}