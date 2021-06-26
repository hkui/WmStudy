<?php
include_once  "Myworker.php";

$w = new Myworker();
$w->count = 10;
Myworker::runAll();