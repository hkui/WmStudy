<?php
pcntl_signal(SIGINT,function($signo){
    echo $signo;
});

var_dump(pcntl_signal_get_handler(SIGINT));
