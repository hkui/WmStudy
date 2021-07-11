<?php
namespace Wm;

class MyworkerBase
{
    /**
     * Standard output stream
     * @var resource
     */
    protected static $_outputStream = null;
    /**
     * If $outputStream support decorated
     * @var bool
     */
    protected static $_outputDecorated = null;
    protected static $daemonize;
    public static $stdoutFile = '/dev/null';
    public static $logFile = '';
    /**
     * PHP built-in error types.
     *
     * @var array
     */
    protected static $_errorType = array(
        E_ERROR             => 'E_ERROR',             // 1
        E_WARNING           => 'E_WARNING',           // 2
        E_PARSE             => 'E_PARSE',             // 4
        E_NOTICE            => 'E_NOTICE',            // 8
        E_CORE_ERROR        => 'E_CORE_ERROR',        // 16
        E_CORE_WARNING      => 'E_CORE_WARNING',      // 32
        E_COMPILE_ERROR     => 'E_COMPILE_ERROR',     // 64
        E_COMPILE_WARNING   => 'E_COMPILE_WARNING',   // 128
        E_USER_ERROR        => 'E_USER_ERROR',        // 256
        E_USER_WARNING      => 'E_USER_WARNING',      // 512
        E_USER_NOTICE       => 'E_USER_NOTICE',       // 1024
        E_STRICT            => 'E_STRICT',            // 2048
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR', // 4096
        E_DEPRECATED        => 'E_DEPRECATED',        // 8192
        E_USER_DEPRECATED   => 'E_USER_DEPRECATED'   // 16384
    );
    /**
     * Safe Echo.
     * @param string $msg
     * @param bool   $decorated
     * @return bool
     */
    public static function safeEcho($msg, $decorated = false)
    {
        $stream = static::outputStream();
        if (!$stream) {
            return false;
        }
        if (!$decorated) {
            $line = $white = $green = $end = '';
            if (static::$_outputDecorated) {
                $line = "\033[1A\n\033[K";
                $white = "\033[47;30m";
                $green = "\033[32;40m";
                $end = "\033[0m";
            }
            $msg = \str_replace(array('<n>', '<w>', '<g>'), array($line, $white, $green), $msg);
            $msg = \str_replace(array('</n>', '</w>', '</g>'), $end, $msg);
        } elseif (!static::$_outputDecorated) {
            return false;
        }
        \fwrite($stream, $msg);
        \fflush($stream);
        return true;
    }
    /**
     * @param null $stream
     * @return bool|resource
     */
    protected static function outputStream($stream = null)
    {
        if (!$stream) {
            $stream = static::$_outputStream ? static::$_outputStream : STDOUT;
        }
        if (!$stream || !\is_resource($stream) || 'stream' !== \get_resource_type($stream)) {
            return false;
        }
        $stat = \fstat($stream);
        if (($stat['mode'] & 0170000) === 0100000) {
            // file
            static::$_outputDecorated = false;
        } else {
            static::$_outputDecorated =\function_exists('posix_isatty') && \posix_isatty($stream);
        }
        return static::$_outputStream = $stream;
    }
    /**
     * Log.
     *
     * @param string $msg
     * @return void
     */
    public static function log($msg)
    {
        $msg = $msg . "\n";
        if (!static::$daemonize) {
            static::safeEcho($msg);
        }
        $data=\date('Y-m-d H:i:s') . ' ' . 'pid:'.  \posix_getpid() . ' ' . $msg;
        \file_put_contents((string)static::$logFile,  $data,FILE_APPEND | LOCK_EX);
    }
    /**
     * Set process name.
     *
     * @param string $title
     * @return void
     */
    protected static function setProcessTitle($title)
    {
        \set_error_handler(function(){});
        // >=php 5.5
        if (\function_exists('cli_set_process_title')) {
            \cli_set_process_title($title);
        } // Need proctitle when php<=5.5 .
        elseif (\extension_loaded('proctitle') && \function_exists('setproctitle')) {
            \setproctitle($title);
        }
        \restore_error_handler();
    }
    /**
     * Check errors when current process exited.
     *
     * @return void
     */
    public static function checkErrors()
    {
        if (static::STATUS_SHUTDOWN !== static::$_status) {
            $error_msg = 'Worker['. \posix_getpid() .'] process terminated' ;
            $errors    = error_get_last();
            if ($errors && ($errors['type'] === E_ERROR ||
                    $errors['type'] === E_PARSE ||
                    $errors['type'] === E_CORE_ERROR ||
                    $errors['type'] === E_COMPILE_ERROR ||
                    $errors['type'] === E_RECOVERABLE_ERROR)
            ) {
                $error_msg .= ' with ERROR: ' . static::getErrorType($errors['type']) . " \"{$errors['message']} in {$errors['file']} on line {$errors['line']}\"";
            }
            static::log($error_msg);
        }
    }
    /**
     * Get error message by error code.
     *
     * @param integer $type
     * @return string
     */
    protected static function getErrorType($type)
    {
        if(isset(self::$_errorType[$type])) {
            return self::$_errorType[$type];
        }

        return '';
    }
    public static function resetStd()
    {
        if (!static::$daemonize) {
            return;
        }
        global $STDOUT, $STDERR;
        $handle = \fopen(static::$stdoutFile, "a");
        if ($handle) {
            unset($handle);
            \set_error_handler(function(){});
            \fclose($STDOUT);
            \fclose($STDERR);
            \fclose(STDOUT);
            \fclose(STDERR);
            $STDOUT = \fopen(static::$stdoutFile, "a");
            $STDERR = \fopen(static::$stdoutFile, "a");
            // change output stream
            static::$_outputStream = null;
            static::outputStream($STDOUT);
            \restore_error_handler();
        } else {
            throw new Exception('can not open stdoutFile ' . static::$stdoutFile);
        }
    }


}