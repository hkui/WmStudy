<?php
/**
 * Date: 2020/1/20
 * Time: 10:33
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

if($pid >0){
    E:
    $r=pcntl_wait($status);
    $err=pcntl_get_last_error();
    if($err==4){
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
