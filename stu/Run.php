<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2020/12/27
 * Time: 20:31
 */

class Run
{
    public static function do($connection, $data){
        $get=$data['get'];
        $s=$get['s']??1;
        $s=intval($s);
        $str='';
        for($i=0;$i<$s;$i++){
            $str.=$i.PHP_EOL;
            sleep(1);
        }
        $connection->send(date("H:i:s")."#-".$str);
    }
}