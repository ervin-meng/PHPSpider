### 日志
### 参数说明
#### (1) msg 
日志内容 <br>
#### (2) name
日志路径及文件名 <br>
/path/to/file 在指定绝对路径创建 <br>
./path/to/file 在当前工作目录记录日志 <br>
../path/to/file 在当前工作目录父级目录记录日志，依次类推 <br>
path/to/file 若果调用init设置了logpath 则记录在logpath 目录下 若未设置logpath 则定义在运行脚本目录下 <br>
#### (3) dateFormat 
日志文件切分规则 <br>
y 按年切割一年一个日志文件 <br>
m 按月切割 <br>
d 按天切割 <br>
h 按小时切割 <br>
非以上参数 不分割
#### (4) ignoreItems 
需要忽略的日志项数组 <br>
logId 日志id用于多系统对接 <br>
line 调用日志的文件和行数 <br>
timeuesed 从日志初始化到调用位置所用时间（单位：毫秒）<br>
time 记录日志时间 <br>
uri 客户端请求uri <br>
rip 客户端ip <br>
#### (5) whiteSpace
日志内容空白符处理 true 处理 false 不处理记录原文 <br>
### 代码示例
```shell
require_once(__DIR__ . '../../../vendor/autoload.php');

use Spider\Utils\Logger;

Logger::write($spider->getContent(),$logFile,'',['line','timeUsed','time','uri','rip'],true);
```
