<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/7
 * Time: 23:25
 */

use Workerman\Worker;
require_once   './../Workerman/Autoloader.php';

for($i=0;$i<20;$i++){
    Worker::safeEcho($i);
}

