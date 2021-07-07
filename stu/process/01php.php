<?php
$arg = $_SERVER['argv'];

file_put_contents("/tmp/exec.txt",
    print_r($arg, 1) . "--ppid=" . posix_getppid() . "--pid=" . posix_getpid() . PHP_EOL,
    FILE_APPEND);

sleep(10);
