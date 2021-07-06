<?php
echo "###".posix_getpid().PHP_EOL;

$cmd="/usr/local/php/bin/php /data/www/WmStudy/stu/process/shell1.php";
$r=exec($cmd,$output);
var_dump($output);
var_dump($r);
