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
    echo "handler:".print_r(pcntl_signal_get_handler(SIGUSR1),1);

};


$http_worker->onMessage = function($connection, $data)
{
//    $get=$data['get'];
//    $s=$get['s']??1;
//    $s=intval($s);
//    $str='';
//    for($i=0;$i<$s;$i++){
//        $str.=$i.PHP_EOL;
//        sleep(1);
//    }
    $connection->send('wm');

};

Worker::runAll();
