<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2020/3/3
 * Time: 12:30
 */

use Workerman\Worker;
use Workerman\Lib\Timer;

require_once   './../Workerman/Autoloader.php';

$task=new Worker();
$task->count=2;
$task->name='hkui';

$task->onWorkerStart=function ($task){
   echo posix_getpid()." started ".PHP_EOL;
};

$task->onWorkerReload=function($task){
    echo posix_getpid()." reload!".PHP_EOL;
};

/*

$task1=new Worker();
$task1->count=2;
$task1->name='hkui1';

$task1->onWorkerStart=function ($task){
    echo posix_getpid()." started ".PHP_EOL;


};
$task1->onWorkerReload=function($task){
    echo "reload!".PHP_EOL;
};
*/

Worker::runAll();