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
$task->name='hki';

$task->onWorkerStart=function ($task){
    // 每2.5秒执行一次
    $time_interval = 2;
    Timer::add($time_interval, function()use($task)
    {
        echo $task->id."--".date("H:i:s")."\n";
    });
};
Worker::runAll();

