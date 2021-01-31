<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/7
 * Time: 23:25
 */

use Workerman\Worker;


require_once   dirname(__DIR__).'/Workerman/Autoloader.php';

// 创建一个Worker监听2345端口，使用http协议通讯

$http_worker = new Worker("http://0.0.0.0:8080");



$http_worker->count = 5;

$http_worker->onConnect = function($connection)
{
    //echo "onConnect:".PHP_EOL;
};
$http_worker->onWorkerReload=function($worker){

};
$http_worker->onClose = function($connection)
{
    //echo "onClose:".PHP_EOL;
};
$http_worker->onWorkerStart=function($worker){
//    echo "handler:".print_r(pcntl_signal_get_handler(SIGUSR1),1);

};

$http_worker->onMessage = function($connection, $data)
{
    Run::do($connection,$data);

};

Worker::runAll();
