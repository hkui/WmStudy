<?php
resetstd("./log.txt");

for($i=0;$i<5;$i++){
    echo "i=".$i."   pid=".posix_getpid() ."  ppid=".posix_getppid().PHP_EOL;
    sleep(2);
}

function resetstd($file)
{
    global $STDOUT, $STDERR;
    $handle = \fopen($file, "a");
    if ($handle) {
        unset($handle);
        \set_error_handler(function(){});
        \fclose($STDOUT);
        \fclose($STDERR);
        \fclose(STDOUT);
        \fclose(STDERR);
        $STDOUT = \fopen($file, "a");
        $STDERR = \fopen($file, "a");
        \restore_error_handler();
    }
}
