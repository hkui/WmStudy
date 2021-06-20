<?php

require_once './../../Workerman/Autoloader.php';

use Workerman\Worker;

$event=new Workerman\Events\Event();

print_r($event);

