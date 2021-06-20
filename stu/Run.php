<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2020/12/27
 * Time: 20:31
 */
date_default_timezone_set("Asia/shanghai");
class Run
{
    public static function do($connection, $data){

//        $start=date("H:i:s");
//        $get=$data['get'];
//        $s=$get['s']??1;
//        $s=intval($s);
//        $str=$start.PHP_EOL;
//        for($i=0;$i<$s;$i++){
//            $str.=$i.PHP_EOL;
//        }
//        $str.=date("H:i:s").PHP_EOL;
        $str=date("Y-m-d H:i:s")."---".posix_getpid().PHP_EOL;
        //$str.="sigint Fun=".print_r(pcntl_signal_get_handler(SIGINT),1).PHP_EOL;
        $str.=PHP_EOL;
        $connection->send($str);
    }
}
