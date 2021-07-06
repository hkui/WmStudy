<?php
use Wm\Myworker;

date_default_timezone_set("Asia/shanghai");
require_once   dirname(__DIR__).'/Wm/Autoloader.php';

$w = new Myworker();
$w->count = 2;
Myworker::$stdoutFile="/tmp/wm.log";
Myworker::runAll();