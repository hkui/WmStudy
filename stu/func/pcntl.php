<?php

echo posix_getpid().PHP_EOL;
pcntl_async_signals(true);

pcntl_signal(SIGINT, function(){
    echo "  [".posix_getpid()."] get signal ".date("H:i:s")."\n";
},false);

$pid = pcntl_fork();

if ($pid) {
    //父进程
    E:
    $wpid=pcntl_wait($status, WUNTRACED); //打断返回-1  否者是回收的pid ,status 被信号打断 值为0
    echo  "pcntl_wait:".pcntl_strerror(pcntl_get_last_error()).PHP_EOL;
    echo "pid=".$wpid." status=".$status.PHP_EOL;
    //被信号打断
    if(pcntl_get_last_error()==4){
        goto E;
    }
    $intstatus=pcntl_wexitstatus(  $status); //子进程退出的exit的值
    echo "exit code =".$intstatus.PHP_EOL;

    //检查是否正常退出

    echo "killed normal = ".var_export(pcntl_wifexited($status),true).PHP_EOL;
    //被杀死就是false

    echo "Interrupted signo = ".pcntl_wtermsig($status).PHP_EOL;

    echo "pcntl_wifstopped = ".var_export(pcntl_wifstopped($status),true).PHP_EOL;
    //WUNTRACED作为 option的pcntl_waitpid()函数调用产生的status


} else {
    echo "son:".posix_getpid().PHP_EOL;
    $i=0;
    while(1){
        echo date("H:i:s").PHP_EOL;
        sleep(1);
        $i++;
        if($i>5){
            exit(10);
        }
    }
}
