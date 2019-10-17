<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/7
 * Time: 23:25
 */

use Workerman\Worker;
require_once   './../Workerman/Autoloader.php';

// 创建一个Worker监听2345端口，使用http协议通讯
$http_worker = new Worker("tcp://0.0.0.0:8081");

// 启动4个进程对外提供服务
$http_worker->count = 5;

$http_worker->onConnect = function($connection)
{
    echo "onConnect:".$connection->getRemoteAddress().PHP_EOL;
};
$http_worker->onClose = function($connection)
{
    echo "onClose:".PHP_EOL;
};
$http_worker->onWorkerStart=function($worker){
    //echo "onWorkerStart:".PHP_EOL; //有几个 count就输出几次
};

// 接收到浏览器发送的数据时回复hello world给浏览器
$http_worker->onMessage = function($connection, $data)
{
    // 向浏览器发送hello world
    $connection->send('hello  ['.$data.']');
};

Worker::runAll();
