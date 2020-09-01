## USAGE<br>
### 1.启动进程
```shell
php yourfile.php start
php yourfile.php start -d 
```
### 2.终止进程
```shell
php yourfile.php stop
```
### 3.重启进程
```shell
php yourfile.php restart
```
### 4.平滑重启 (未完善)
```shell
php yourfile.php reload
```

## 说明<br>
### 1.启用多个子进程
```shell
$process->run($job,$count);//job是要执行的任务，可以被call_user_func_array执行的参数，$count指定每个任务启用几个子进程去执行。
```
### 2.子进程意外中断，重新创建子进程
在任务代码里请不要调用exit退出，因为父进程监听到不是指定status状态退出，会重新创建子进程。
### 3.进程间通信
(1) 管道<br>
用posix_mkfifo实现，一段写入一段读取，没有写入会阻塞读端。半双工。<br>
(2) 共享内存<br>
需要安装shmop扩展目前未完成。<br>
(3) 消息队列<br>
无<br>
(4) socket<br>
用stream_socket_pair实现，全双工读端不阻塞。<br>
