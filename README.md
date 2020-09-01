PHP Spider
===
### 注意事项
1.目前只是简陋开发版本,还有很多功能代码未完善。<br>
2.多进程只能在Linux运行。<br>
3.需要安装PHP Redis扩展,目前所有存储容器的实现都依赖于Redis，此点会慢慢改进。<br>
4.依赖第三方库 guzzleHttp <br>
### 功能介绍
1.支持代理。启用代理后,每次抓取随机获取代理IP(有可能和上次一样)，如果用代理抓取失败，会重新加入URL抓取列表，并使用新的代理再次抓取，详细说明请点击[这里](https://github.com/ervin-meng/pspider/blob/master/src/Proxy/README.md)。<br>
2.支持DOM操作,xpath css选择器(未完善)，详细说明请点击[这里](https://github.com/ervin-meng/pspider/blob/master/src/Parsers/README.md)。<br>
3.支持多进程，守护进程方式，进程启动、停止、重启，平滑重启（未完善）。进程意外中断会重启子进程继续爬取，详细说明请点击[这里](https://github.com/ervin-meng/pspider/blob/master/src/Multiprocess/README.md)。<br>
4.支持钩子,onStart,beforeCrawl,afterCrawl,afterDiscover。<br>
5.支持Redis,MySQL。<br>
6.内置User-Agent,分为PC端和手机端,每次抓取自动切换User-Agent。<br>
7.支持设置爬取数量上限,设置每个进程的抓取间隔,如果只抓取当前页面,可以设置discover属性为false.<br>
8.设置各种header信息和超时时间等,cookie可以直接传数组。<br>
9.当超过抓取上限或URL队列为空时,进程自动停止。<br>
### 依赖安装
```shell
composer require ervin-meng/pspider:dev-master
```

### 代码示例
```shell
    require_once(__DIR__ . '../../../vendor/autoload.php');

    use PSpider\Spider;
    use PSpider\Utils\Hook;
    use PSpider\Utils\Logger;

    $seeds = [
        'http://blog.jobbole.com/all-posts/',
        'https://tech.imdada.cn/',
        'https://tech.meituan.com/'
    ];
    
    $options = ['proxy'=>false,'verify'=>false];
    
    $patterns = [
        '/^https:\/\/tech.meituan.com\/(.*)+\.html$/',
        '/^https:\/\/tech.imdada.cn\/(\d{4})\/(\d{2})\/(\d{2})\/(.*)+\/$/',
        '/^http:\/\/blog.jobbole.com\/(\d*)\/$/'
    ];
    
    $spider = new Spider($seeds,$options,$patterns);
    
    Hook::register('afterCrawl',function()use($spider){

        $date = date('Y-m-d');
        $params = $spider->getParams();

        $logFile = __DIR__.'/pages/'.$date.'/'.md5($params['url']).'.page';
        Logger::write($spider->getContent(),$logFile,'',['line','timeUsed','time','uri','rip'],true);
        
        $logMsg = "[PID:{$spider->getWorkerId()}]\t[PAGE:{$params['url']}]\t[PROXY:{$params['options']['proxy']}]";
        $logFile = __DIR__.'/logs/'.$date.'.log';
        Logger::write($logMsg,$logFile,'',['uri','rip'],true);
    });
    
    $spider->exec(5); 
```
### 运行示例
#### 1.启动爬虫
##### 1.1 CLI 模式（目前只支持linux系统）：
(1) 非守护进程方式(停留终端)
```shell
php yourfile.php start 
```
(2) 守护进程方式(脱离终端)
```shell
php yourfile.php start -d
```
##### 1.2 CGI 模式：
此模式不支持多进程。
#### 2.停止爬虫
```shell
php yourfile.php stop
```
