<?php
use Wm\Myworker;

date_default_timezone_set("Asia/shanghai");
require_once   dirname(__DIR__).'/Wm/Autoloader.php';

$w = new Myworker("http://0.0.0.0:8080");

$w->count = 2;

Myworker::$stdoutFile="/tmp/wm.log";
$w->onMessage = function($connection, $data)
{
    print_r($connection);

};
Myworker::runAll();