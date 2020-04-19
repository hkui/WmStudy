<?php
/**
 * Date: 2020/1/20
 * Time: 10:33
 * 子进程继承父进程的信号处理函数，信号是后来的子进程不会收到的
 */
pcntl_async_signals(true);
$master_pid=posix_getpid();
echo $master_pid.PHP_EOL;

pcntl_signal(SIGUSR1,function($signo){
    echo " ".posix_getpid()." receive ".$signo.PHP_EOL;
},false);

pcntl_signal(SIGINT,function($signo) use($master_pid){
    echo " ".posix_getpid()." receive ".$signo.PHP_EOL;
    if($master_pid !=posix_getpid()){
        exit(2);
    }

},false);

$pid=pcntl_fork();
if($pid<0) die("fork err");

//父进程
if($pid >0){
    E:
    $r=pcntl_wait($status);
    $err=pcntl_get_last_error();
    if($err==4){
        echo "信号打断".PHP_EOL;
        goto E;
    }
    echo $r."--".$err.PHP_EOL;
}else{
    $pid=posix_getpid();
    $i=0;
    while(true){
        echo $i."---".$pid.PHP_EOL;
        sleep(2);
        $i++;
    }
    exit();
}
