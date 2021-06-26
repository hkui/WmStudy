<?php
$r=fstat(STDOUT);
print_r($r);

$f=fopen('heap.php','a');
$r=fstat($f);
print_r($r);