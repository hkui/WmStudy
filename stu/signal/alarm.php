<?php
pcntl_async_signals(true);
pcntl_signal(SIGALRM,"handle",false);
pcntl_alarm(2);

function handle($signo){
    echo date("Y-m-d H:i:s")."   ".posix_getpid()." get ".$signo.PHP_EOL;
//    pcntl_alarm(2);
}
while(true){
    echo date("Y-m-d H:i:s").PHP_EOL;
    sleep(1);
}
