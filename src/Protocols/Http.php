<?php
namespace PHPSpider\Protocols;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use PHPSpider\Utils\Logger;

class Http
{
    static protected $_requestHandler = null;

    static public $userAgents = [
        'pc' => [
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/51.0.2704.103 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/537.75.14',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:29.0) Gecko/20100101 Firefox/29.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/537.36',
            'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
            'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1)',
            'Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0)',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)',
            'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; rv:11.0) like Gecko',
        ],
        'android' => [
            'Mozilla/5.0 (Android; Mobile; rv:29.0) Gecko/29.0 Firefox/29.0',
            'Mozilla/5.0 (Linux; Android 4.4.2; Nexus 4 Build/KOT49H) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.114 Mobile Safari/537.36'
        ],
        'ios' => [
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) CriOS/34.0.1847.18 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (iPad; CPU OS 7_0_4 like Mac OS X) AppleWebKit/537.51.1 (KHTML, like Gecko) Version/7.0 Mobile/11B554a Safari/9537.53',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0_2 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.4 (KHTML, like Gecko) Version/8.0 Mobile/12A366 Safari/600.1.4'
        ],
        'baidu' => ['Mozilla/5.0+(compatible;+Baiduspider/2.0;++http://www.baidu.com/search/spider.html)'],
        'google' => ['Mozilla/5.0+(compatible;+Googlebot/2.1;++http://www.google.com/bot.html)'],
        'sougou' => ['Sogou+web+spider/4.0(+http://www.sogou.com/docs/help/webmasters.htm#07)'],
        'bing' => ['Mozilla/5.0+(compatible;+bingbot/2.0;++http://www.bing.com/bingbot.htm)']
    ];

    /**
     * @brief ����URL��ַ�е�QUERY
     */
    static public function builderUrlQuery($data,$arg_separator = '&',$numeric_prefix = null,$enc_type = PHP_QUERY_RFC1738)
    {
        if(!is_string($data) && (is_object($data) || is_array($data)))
        {
            $query = http_build_query($data,$numeric_prefix,$arg_separator,$enc_type);
        }
        else
        {
            $query = $data;
        }

        return $query;
    }

    /**
     * @breif ��ȡURL��ַ�е�QUERY
     */
    static public function parseUrlQuery($url,$arr=false)
    {
        $query = parse_url($url,PHP_URL_QUERY);

        if($arr!==false)
        {
            parse_str($query,$arr);
            $query = $arr;
        }

        return $query;
    }

    /**
     * @brief ��ȡURL��ַ�е�HOST
     */
    static public function parseUrlHost($url)
    {
        return parse_url($url,PHP_URL_HOST);
    }

    static public function builderUserAgent($type = 'pc')
    {
        if(array_key_exists($type,self::$userAgents))
        {
            $ua = self::$userAgents[$type][array_rand(self::$userAgents[$type])].rand(0, 10000);
        }
        else if($type=='mobile')
        {
            $userAgentArray = array_merge(self::$userAgents['android'],self::$userAgents['ios']);
            $ua = $userAgentArray[array_rand($userAgentArray)].rand(0, 10000);
        }
        else{
            $ua = $type;
        }

        return $ua;
    }

    /**
     * @brief ����HTTP����
     *
     * $options['verify'] ssl
     * $options['timeout']
     * $options['cookies']
     * $options['form_params']
     */
    public static function request($method,$url,$options,$log_path='http/request',$async = false)
    {
        if (is_null(self::$_requestHandler)) {
           self::$_requestHandler = new Client;
        }

        try {

            if ($async) {
                return self::$_requestHandler->requestAsync($method,$url,$options);
            } else {
                $response = self::$_requestHandler->request($method,$url,$options);
            }

            return $response->getBody()->getContents();

        } catch(RequestException $e) {

            Logger::error(['errmsg'=>$e->getMessage()],$log_path,'d');

            return false;
        }
    }

    /**
     * @brief ��ȡ�ͻ���IP��ַ
     */
    static public function getClientIp()
    {
        if (isset($_SERVER['HTTP_CLIENT_IP']) && !empty($_SERVER['HTTP_CLIENT_IP']))
        {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR']) && !empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        {
            $ip = strtok($_SERVER['HTTP_X_FORWARDED_FOR'], ',');
            do
            {
                $ip = trim($ip);
                $ip = ip2long($ip);
                /*
                 * 0xFFFFFFFF = 4294967295  	255.255.255.255
                 * 0x7F000001 = 2130706433	 	127.0.0.1
                 * 0x0A000000 = 167772160		10.0.0.0
                 * 0x0AFFFFFF = 184549375		10.255.255.255
                 * 0xC0A80000 = 3232235520		192.168.0.0
                 * 0xC0A8FFFF = 3232301055		192.168.255.255
                 * 0xAC100000 = 2886729728		172.16.0.0
                 * 0xAC1FFFFF = 2887778303		172.31.255.255
                 */
                if (!(($ip == 0) || ($ip == 0xFFFFFFFF) || ($ip == 0x7F000001) ||
                    (($ip >= 0x0A000000) && ($ip <= 0x0AFFFFFF)) ||
                    (($ip >= 0xC0A80000) && ($ip <= 0xC0A8FFFF)) ||
                    (($ip >= 0xAC100000) && ($ip <= 0xAC1FFFFF))))
                {
                    return long2ip($ip);
                }
            } while ($ip = strtok(','));
        }
        if (isset($_SERVER['HTTP_PROXY_USER']) && !empty($_SERVER['HTTP_PROXY_USER']))
        {
            return $_SERVER['HTTP_PROXY_USER'];
        }
        if (isset($_SERVER['REMOTE_ADDR']) && !empty($_SERVER['REMOTE_ADDR']))
        {
            return $_SERVER['REMOTE_ADDR'];
        }
        else
        {
            return "0.0.0.0";
        }
    }
}