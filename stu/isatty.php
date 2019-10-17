<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/8
 * Time: 23:11
 */

if ( !posix_isatty(STDOUT) ) {
    fwrite(STDOUT, "Invalid TTY\n");
    exit(2);
}
fwrite(STDOUT, "Enter you name\n");
$name = fgets(STDIN);
fwrite(STDOUT,"Hello $name\n");
exit(0);

/**
[root@hkui stu]# php isatty.php >tmp
[root@hkui stu]# cat tmp
Invalid TTY
 */
?>

