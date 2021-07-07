<?php

echo str_repeat('#', 10) . posix_getpid() . str_repeat('#', 10) . PHP_EOL;
$pid = pcntl_fork();

if ($pid < 0) die("fork err");
if ($pid == 0) {
    $child_pid = posix_getpid();
    echo "child " . $child_pid . PHP_EOL;
    $command = '/usr/local/php/bin/php  ./01php.php';
    sleep(2);
//    exec($command . ' ' . $child_pid);

    pcntl_exec('/usr/local/php/bin/php',['./01php.php']);

    echo "child " . posix_getpid() . " will exit" . PHP_EOL;

} else {
    $parent_pid = posix_getpid();
    echo "parent begin:" . $parent_pid . PHP_EOL;
    $i = 0;
    while ($i < 2) {
        echo $i . '--parent--' . $parent_pid . PHP_EOL;
        sleep(1);
        $i++;
    }
    echo "waiting child exit" . PHP_EOL;
    $exit_id = pcntl_waitpid($pid, $status);
    if ($exit_id) {
        echo "in parent child " . $exit_id . " exited" . PHP_EOL;
    }
    echo "parent end:" . posix_getpid() . PHP_EOL;
}