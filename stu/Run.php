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

        $start=date("H:i:s");
        $get=$data['get'];
        $s=$get['s']??1;
        $s=intval($s);
        $str=$start.PHP_EOL;
        for($i=0;$i<$s;$i++){
            $str.=$i.PHP_EOL;
//            sleep(1);
        }
        $str.=date("H:i:s").PHP_EOL;
        $connection->send($str);
    }
}
