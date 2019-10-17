<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/8
 * Time: 22:06
 */

$stat=fstat(STDOUT);

echo decbin($stat['mode']);// 二进制 0010 0001 1001 0000   12~15位：文件类型  即为 0010
echo PHP_EOL;
echo "8进制=".decoct($stat['mode']);// 8进制 00020620   字符设备文件
echo PHP_EOL;
var_dump(($stat['mode'] & 0170000)=== 0100000); //false


$a=fopen('./a',"a+",0);
$stat1=fstat($a);
echo decbin ($stat1['mode']); //1000 0001 1010 0100
echo PHP_EOL;
echo "8进制=".decoct($stat1['mode']); //8进制
echo PHP_EOL;
var_dump(($stat1['mode'] & 0170000)=== 0100000); //true

/**
可直接使用宏名
取出12~15位，判断文件类型
使用屏蔽字0170000(1111 000000000000)&st_mode,将0~13清0(屏蔽)，留下的12~15即为文件类型
宏 S_IFMT 0170000
 */


