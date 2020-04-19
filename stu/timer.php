<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/9
 * Time: 22:54
 */

use Workerman\Worker;
use Workerman\Lib\Timer;

require_once   './../Workerman/Autoloader.php';

$task=new Worker();
$task->count=2;
$task->name='hkui';

$task->onWorkerStart=function ($task){
//    $time_interval = 3;
//    Timer::add($time_interval, function()use($task)
//    {
//        $str= posix_getpid()."--".date("H:i:s")."\n";
//        file_put_contents("./timer.log",$str,FILE_APPEND);
//    },false);


};
$task->onWorkerReload=function($task){
    $str= posix_getpid()."--###RELOAD####".PHP_EOL;
    file_put_contents("./timer.log",$str,FILE_APPEND);
};

Worker::runAll();

