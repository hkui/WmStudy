<?php
use Workerman\Worker;
use Workerman\Lib\Timer;

require_once   './../Workerman/Autoloader.php';


$globalEvent = new \Workerman\Events\Select();

Timer::init($globalEvent);
Timer::add(0.5,function(){
    echo "1---".date("H:i:s").PHP_EOL;
    sleep(2);
});
//Timer::add(2,function(){
//    echo "2---".date("H:i:s").PHP_EOL;
//});


$globalEvent->loop();
