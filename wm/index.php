<?php
include_once  "Myworker.php";

$w = new Myworker();
$w->count = 2;
Myworker::run();