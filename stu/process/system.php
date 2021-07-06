<?php
echo "###".posix_getpid().PHP_EOL;
$dir="./../func";
$return=0;
$cmd="/usr/local/php/bin/php /data/www/WmStudy/stu/process/shell1.php";
$r=system($cmd,$return);
var_dump($return);
var_dump($r);