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

$http_worker = new Worker("http://0.0.0.0:8080");



$http_worker->count = 1;

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
    //echo "onWorkerStart:".PHP_EOL; //有几个 count就输出几次
};


$http_worker->onMessage = function($connection, $data)
{
    $str='<br>'.PHP_EOL.PHP_EOL;
    for($i=0;$i<10;$i++){
        $tmp=$i."-----".date("Y-m-d H:i:s")."----".posix_getpid()."<br>".PHP_EOL;
        $str.=$tmp;
        file_put_contents("/tmp/wm.txt",$tmp,FILE_APPEND);
        sleep(1);
    }
    $connection->send($str.date("Y-m-d H:i:s"));
};

Worker::runAll();
