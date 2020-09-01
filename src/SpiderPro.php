<?php
namespace PHPSpider;

use GuzzleHttp;
use GuzzleHttp\Cookie\CookieJar;
use PHPSpider\Proxy\Proxy;
use PHPSpider\Protocols\Http;
use PHPSpider\Multiprocess\Process;
use PHPSpider\Parsers\HtmlParser;
use PHPSpider\Container\Collection;
use PHPSpider\Container\LineList;
use PHPSpider\Utils\Format;
use PHPSpider\Utils\Hook;
use PHPSpider\Utils\Logger;
use PHPSpider\Utils\Debug;
use PHPSpider\Utils\Config;
use PHPSpider\Exception\SpiderException;

class SpiderPro
{
    public $name = 'SpiderPro';
    public $crawlLimit = 0;
    public $interval = 5;
    public $discover = true;
    public $repeat = false;
    public $logfile = 'exception_pro.log';
    public $info = [];

    protected $_params = [];
    protected $_content;
    protected $_downloading;

    protected $_config;
    protected $_seeds = [];
    protected $_options = [
        'proxy' => false,
        'verify' => false,
        'timeout' => 8,
        'headers'=>['User-Agent'=>'pc']
    ];
    protected $_helpParttern = '';
    protected $_crawlParttern = '';

    protected $_crawlCollection;
    protected $_helpCollection;
    protected $_discoverList;

    protected $_process = null;
    protected $_proxy = null;

    public function __construct($seeds,$options=[],$helpPattern='',$crawlPattern='')
    {
        set_exception_handler([$this,'exceptionHandler']);

        foreach ((array)$seeds as $seed)
        {
            if(is_string($seed))
            {
                $seed = ['method'=>'get','url'=>$seed,'options'=>[]];
            }

            $this->_seeds[] = json_encode($seed);
        }

        $this->_options = array_merge($this->_options,$options);
        $this->_helpParttern = $helpPattern;
        $this->_crawlParttern = $crawlPattern;

        Hook::register('onStart',[$this,'start']);
        Hook::register('onStop',[$this,'stop']);
    }

    public function exec($workers = 0,$alarm = 0)
    {
        if($workers>0)
        {
            $this->_process = new Process($this->name);
            $this->_process->run([[$this,'run']],$workers,$alarm);
        }
        else
        {
            Hook::invoke('onStart');
            $this->run();
            Hook::invoke('onStop');
        }
    }

    public function run()
    {
        while($this->_discoverList->len()>0)
        {
            try
            {
                $this->crawl();
            }
            catch(\Exception $e)
            {
                $this->exceptionHandler($e);
            }

            sleep($this->interval);
        }
    }

    public function start()
    {
        $this->_discoverList = new LineList($this->name.'_Discover','redis',Config::get('redis'));
        $this->_crawlCollection = new Collection($this->name.'_Crawl','redis',Config::get('redis'));
        $this->_helpCollection = new Collection($this->name.'_Help','redis',Config::get('redis'));

        $this->_discoverList->clean();
        $this->_helpCollection->clean();

        if($this->repeat)
        {
            $this->_crawlCollection->clean();
        }

        foreach($this->_seeds as $seed)
        {
            $this->_discoverList->add($seed);;
        }

        $this->info['startcount'] = $this->_crawlCollection->count();
        Debug::begin('spider');
    }

    public function stop()
    {
        $this->info['endcount'] = $this->_crawlCollection->count();
        Debug::end('spider',$this->info);
    }

    public function crawl()
    {
        Hook::invoke('beforeCrawl');

        if ($this->crawlLimit > 0 && $this->_crawlCollection->count() >= $this->crawlLimit)
        {
            throw new SpiderException("[PID:{$this->getWorkerId()}] The number of crawling exceeds the crawl limit");
        }

        do
        {
            $while = false;

            if($this->_discoverList->len()==0)
            {
                throw new SpiderException("[PID:{$this->getWorkerId()}] The discover list is empty");
            }

            $this->_downloading = json_decode($this->_discoverList->next(),true);

            if($this->_crawlCollection->isMember(json_encode($this->_downloading)))
            {
                $while = true;
            }
            else if($this->_helpCollection->isMember(json_encode($this->_downloading)))
            {
                $while = true;
            }
            else if(empty($this->_downloading['url']))
            {
                $while = true;
            }

        }while($while);

        $url = $this->_downloading['url'];
        $method = !empty($this->_downloading['method']) ? $this->_downloading['method'] : 'GET';
        $options = is_array($this->_downloading['options'])?array_merge($this->_options,$this->_downloading['options']):$this->_options;

        $this->_userAgentDecorate($options);
        $this->_proxyDecorate($options);
        $this->_cookieDecorate($options,$url);

        $this->_params = ['method'=>$method,'url'=>$url,'options'=>$options];
        $this->_content = Http::request($method,$url,$options);

        if(preg_match($this->_crawlParttern,$url))
        {
            $this->_crawlCollection->add(json_encode($this->_downloading));
            Hook::invoke('afterCrawl');
        }
        else{
            $this->_helpCollection->add(json_encode($this->_downloading));
            $this->discover();
        }
    }

    public function discover()
    {
        if(!$this->discover)
        {
            return false;
        }

        $urls = HtmlParser::load($this->_content)->findText('//a/@href');
        $urls = Format::url($urls,$this->_downloading['url']);
        $urls = array_unique($urls);

        $method = isset($this->_downloading['method']) ? $this->_downloading['method']:'';
        $options = isset($this->_downloading['options'])? $this->_downloading['options']:[];

        foreach ($urls as $url)
        {
            $seed = json_encode(['url'=>$url,'method'=>$method,'options'=>$options]);

            if($this->_crawlCollection->isMember($seed))
            {
                continue;
            }

            if(empty($this->_helpParttern) && empty($this->_crawlParttern))
            {
                $this->_discoverList->add($seed);
            }
            else if($this->_helpParttern && preg_match($this->_helpParttern,$url))
            {
                $this->_discoverList->add($seed);
            }
            else if($this->_crawlParttern && preg_match($this->_crawlParttern,$url))
            {
                $this->_discoverList->add($seed);
            }
        }
        Hook::invoke('afterDiscover');
    }

    public function exceptionHandler($e)
    {
        $logMsg = '';

        if($e instanceof \RedisException)
        {
            $logMsg = "[Exception:Redis] [MSG:{$e->getMessage()}]";
        }
        else if($e instanceof SpiderException)
        {
            $logMsg = "[Exception:Spider] [MSG:{$e->getMessage()}]";
            Logger::except($logMsg,__DIR__ .'/'.$this->logfile,'d',['uri','rip'],true);
            exit();
        }
        else if($e instanceof GuzzleHttp\Exception\ConnectException)
        {
            $logMsg = "[Exception:Connect] [MSG:{$e->getMessage()}] [URL:{$this->_params['url']}] [PROXY:{$this->_params['options']['proxy']}]";
            $this->_discoverList->add(json_encode($this->_downloading));
        }
        else if($e instanceof GuzzleHttp\Exception\ClientException)
        {
            if ($e->getResponse()->getStatusCode()!=404)
            {
                $logMsg = "[Exception:Client] [MSG:{$e->getMessage()}] [URL:{$this->_params['url']}] [PROXY:{$this->_params['options']['proxy']}]";
                $this->_discoverList->add(json_encode($this->_downloading));
            }
        }
        else{
            $logMsg = "[Exception:".get_class($e)."] [MSG:{$e->getMessage()}] [File:{$e->getFile()} {$e->getLine()}]";
        }

        if(!empty($logMsg))
        {
            Logger::except($logMsg,__DIR__ .'/'.$this->logfile,'d',['uri','rip'],true);
        }
    }

    protected function _proxyDecorate(&$options)
    {
        if (isset($options['proxy']) && $options['proxy'] === true) {
            $https = false;
            if (is_string($this->_downloading['url'])) {
                $https = strpos($this->_downloading['url'],'https')?true:false;
            }
            if (is_null($this->_proxy)) {
                $this->_proxy = new Proxy();
            }
            $options['proxy'] = $this->_proxy->get($https);
        }
    }

    protected function _userAgentDecorate(&$options)
    {
        if (isset($options['headers']['User-Agent']) && $options['headers']['User-Agent']) {
            $options['headers']['User-Agent'] = Http::builderUserAgent($options['headers']['User-Agent']);
        }
    }

    protected function _cookieDecorate(&$options, $url)
    {
        if (isset($options['cookies']) && is_array($options['cookies'])) {
            $host = parse_url($url,PHP_URL_HOST);
            $options['cookies'] = CookieJar::fromArray($options['cookies'],$host);
        }
    }

    public function getWorkerId()
    {
        $id = 0;

        if (is_object($this->_process)) {
            $id = $this->_process->getPid();
        }

        return $id;
    }

    public function getParams()
    {
        return $this->_params;
    }

    public function getContent()
    {
        return $this->_content;
    }
}

