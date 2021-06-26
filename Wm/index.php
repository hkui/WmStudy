<?php
use Wm\Myworker;


require_once   dirname(__DIR__).'/Wm/Autoloader.php';

$w = new Myworker();
$w->count = 2;
Myworker::runAll();