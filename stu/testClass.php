<?php
class T{
    public static $w=[];
    public function __construct(){
        self::$w=["a","b"];
    }
    public static function getW(){
        return self::$w;
    }

}
$t=new T();
print_r(T::getW());
