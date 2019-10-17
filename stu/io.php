<?php
/**
 * Created by PhpStorm.
 * User: 764432054@qq.com
 * Date: 2019/10/18
 * Time: 0:20
 */



\set_error_handler('myErrorHandler');
fclose($STDOUT);


function myErrorHandler($errno, $errstr, $errfile, $errline)
{
    if (!(error_reporting() & $errno)) {
        return false;
    }

    switch ($errno) {
        case E_USER_ERROR:
            echo "My ERROR:[$errno] $errstr<br />\n";
            echo "  Fatal error on line $errline in file $errfile";
            echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
            echo "Aborting...<br />\n";
            exit(1);
            break;

        case E_USER_WARNING:
            echo "My WARNING: [$errno] $errstr\n";
            break;

        case E_USER_NOTICE:
            echo "My NOTICE [$errno] $errstr\n";
            break;
        case E_NOTICE:
            echo "notice: [$errno] $errstr\n";
            break;

        default:
            echo "Unknown error type: [$errno] $errstr\n";
            break;
    }


    return true;
}