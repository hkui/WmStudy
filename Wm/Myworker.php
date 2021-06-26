<?php
namespace Wm;

require_once __DIR__ . '/Lib/Constants.php';

use Wm\Connection\ConnectionInterface;
use Wm\Lib\Timer;

use Exception;

class Myworker
{
    /**
     * Status starting.
     *
     * @var int
     */
    const STATUS_STARTING = 1;

    /**
     * Status running.
     *
     * @var int
     */
    const STATUS_RUNNING = 2;

    /**
     * Status shutdown.
     *
     * @var int
     */
    const STATUS_SHUTDOWN = 4;

    /**
     * Status reloading.
     *
     * @var int
     */
    const STATUS_RELOADING = 8;
    /**
     * Current status.
     *
     * @var int
     */
    protected static $_status = self::STATUS_STARTING;
    /**
     * Start file.
     *
     * @var string
     */
    protected static $_startFile = '';

    public $count = 2;
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

    public static $_workers = [];
    /**
     * @var array  [worker_id=>[pid=>pid, pid=>pid, ..], ..]
     */
    public static $_pidMap=[];
    /**
     * @var array [worker_id=>[0=>$pid0, 1=>$pid1, ..], ..].
     */
    protected static $_idMap = [];
    /**
     * All worker processes waiting for restart.
     * The format is like this [pid=>pid, pid=>pid].
     *
     * @var array
     */
    protected static $_pidsToRestart = array();
    public $workerId;
    protected static $pidFile='./master_pid';
    protected static $daemonize;
    public static $stdoutFile = '/dev/null';
    public static $logFile = '';
    public static $_gracefulStop;
    protected static $_masterPid = 0;
    /**
     * Is worker stopping ?
     * @var bool
     */
    public $stopping = false;
    /**
     * Emitted when worker processes stoped.
     *
     * @var callable
     */
    public $onWorkerStop = null;

    /**
     * Emitted when worker processes get reload signal.
     *
     * @var callable
     */
    public $onWorkerReload = null;
    /**
     * Emitted when worker processes start.
     *
     * @var callable
     */
    public $onWorkerStart = null;

    /**
     * Emitted when a socket connection is successfully established.
     *
     * @var callable
     */
    public $onConnect = null;

    /**
     * Emitted when data is received.
     *
     * @var callable
     */
    public $onMessage = null;

    /**
     * Emitted when the other end of the socket sends a FIN packet.
     *
     * @var callable
     */
    public $onClose = null;

    /**
     * Emitted when an error occurs with connection.
     *
     * @var callable
     */
    public $onError = null;

    /**
     * Emitted when the send buffer becomes full.
     *
     * @var callable
     */
    public $onBufferFull = null;

    /**
     * Emitted when the send buffer becomes empty.
     *
     * @var callable
     */
    public $onBufferDrain = null;
    /**
     * Store all connections of clients.
     *
     * @var array
     */
    public $connections = array();
    /**
     * Global event loop.
     *
     * @var Events\EventInterface
     */
    public static $globalEvent = null;

    public function __construct()
    {
        $this->workerId                    = \spl_object_hash($this);
        static::$_workers[$this->workerId] = $this;
        static::$_pidMap[$this->workerId]  = [];
    }

    public static function runAll()
    {
        static::init();
        static::parseCommand();
        static::installSignal();
        static::daemonize();
        static::forkWorkers();
        static::resetStd();
        static::wait();
    }
    public static function daemonize(){
        if(!self::$daemonize){
            return ;
        }
        umask(0);
        $pid=pcntl_fork();
        if($pid<0){
            throw  new  \Exception("fork err");
        }
        //parent 与i出
        if($pid>0){
            exit();
        }
        //son 设置会话组，成为组长
        if(posix_setsid()<0){
            throw  new  \Exception("setsid err");
        }
        $pid= pcntl_fork();
        if($pid<0){
            throw  new  \Exception("fork err");
        }
        //parent 退出
        if($pid>0){
            exit();
        }
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
    protected static function init1(){
        static::initId();
    }
    protected static function init()
    {
        \set_error_handler(function($code, $msg, $file, $line){
            static::safeEcho("$msg in file $file on line $line\n");
        });

        // Start file.
        $backtrace        = \debug_backtrace();
        static::$_startFile = $backtrace[\count($backtrace) - 1]['file'];


        $unique_prefix = \str_replace('/', '_', static::$_startFile);


        // Pid file.
        if (empty(static::$pidFile)) {
            static::$pidFile = __DIR__ . "/../$unique_prefix.pid";
        }

        // Log file.
        if (empty(static::$logFile)) {
            static::$logFile = __DIR__ . '/../wm.log';
        }
        $log_file = (string)static::$logFile;
        if (!\is_file($log_file)) {
            \touch($log_file);
            \chmod($log_file, 0622);
        }

        // State.
        static::$_status = static::STATUS_STARTING;


        // Process title.
        static::setProcessTitle('WorkerMan: master process  start_file=' . static::$_startFile);

        // Init data for worker id.
        static::initId();

        // Timer init.
        Timer::init();
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
    protected static  function initId(){
        foreach (self::$_workers as $worker_id=>$worker){
            $newIdMap=[];
            $count=$worker->count<1?1:$worker->count;
            for($i=0;$i<$count;$i++){
                $id=isset(static::$_idMap[$worker_id][$i])?static::$_idMap[$worker_id][$i]:0;
                $newIdMap[$i]=$id;
            }
            self::$_idMap[$worker_id]=$newIdMap;
        }
    }
    protected static function getId($workerid,$pid){
        return array_search($pid,static::$_idMap[$workerid]);
    }


    protected static function installSignal() {
        pcntl_async_signals(true);
        // stop
        \pcntl_signal(SIGINT, [self::class,"signalHandler"], false);
        // graceful stop
        \pcntl_signal(SIGTERM, [self::class,"signalHandler"], false);
        // reload
        \pcntl_signal(SIGUSR1, [self::class,"signalHandler"], false);
        // graceful reload
        \pcntl_signal(SIGQUIT, [self::class,"signalHandler"], false);
        // status
        \pcntl_signal(SIGUSR2, [self::class,"signalHandler"], false);
        // connection status
        \pcntl_signal(SIGIO, [self::class,"signalHandler"], false);
        // ignore
        \pcntl_signal(SIGPIPE, SIG_IGN, false);
    }


    public static function wait()
    {
        foreach (self::$_workers as $worker){
            self::waitOne($worker);
        }
    }
    /**
     * @param $worker self
     */
    public static function waitOne($worker){
        while (true) {
            $waitpid = pcntl_wait($status, WUNTRACED);
            echo "waitPid=".$waitpid.PHP_EOL;
            //主进程自己被信号打断
            if ($waitpid == -1) {
                continue;
            }
            if ($waitpid>0) {
                $pid_array=static::$_pidMap[$worker->workerId];
                if(isset($pid_array[$waitpid])){
                    unset(self::$_pidMap[$worker->workerId][$waitpid]);
                    $id=static::getId($worker->workerId,$waitpid);
                    static::$_idMap[$worker->workerId][$id]=0; //释放出来1个坑位
                    echo $waitpid . "    exit !" . PHP_EOL;
                    if (empty(self::$_workers[$worker->workerId])) {
                        break;
                    }
                    //重新拉起
                    static::forkWorkers();
                }

            } else {
                echo $waitpid . PHP_EOL;
            }
        }
    }

    public static function forkWorkers()
    {
        foreach (self::$_workers as $worker) {
            self::forkOneWorker($worker);
        }
    }

    /**
     * @param $worker self
     */
    public static function forkOneWorker($worker){
        while( ($id=static::getId($worker->workerId,0))!==false){
            $pid = pcntl_fork();
            if ($pid < 0){
                die("fork err!");
            }
            //parent
            if ($pid > 0) {
                self::$_pidMap[$worker->workerId][$pid]=$pid;
                self::$_idMap[$worker->workerId][$id]=$pid;
            } else {
                static::resetStd();
                //son
                $i = 0;
                while (1) {
                    echo $i . "--" . posix_getpid() . PHP_EOL;
                    sleep(3);
                    $i++;
                }
                exit();
            }
        }
    }
    public static function signalHandler($signal)
    {
        static::log("line=".__LINE__." get signal=".$signal." pid=".posix_getpid());

        switch ($signal) {
            // Stop.
            case SIGINT:
                static::$_gracefulStop = false;
                static::stopAll();
                break;
            // Graceful stop.
            case SIGTERM:
                static::$_gracefulStop = true;
                static::stopAll();
                break;
            // Reload.
            case SIGQUIT:
            case SIGUSR1:
                if($signal === SIGQUIT){
                    static::$_gracefulStop = true;
                }else{
                    static::$_gracefulStop = false;
                }
                static::$_pidsToRestart = static::getAllWorkerPids();
                echo "pidToRestart=".join(",",static::$_pidsToRestart).PHP_EOL;
                static::reload();
                break;
            // Show status.
            case SIGUSR2:
                static::writeStatisticsToStatusFile();
                break;
            // Show connection status.
            case SIGIO:
                static::writeConnectionsStatisticsToStatusFile();
                break;
        }
    }

    public static function stopAll()
    {
        static::$_status = static::STATUS_SHUTDOWN;
        // For master process.
        if (static::$_masterPid === \posix_getpid()) {
            static::log(\basename(static::$_startFile) . "] stopping ...");
            $worker_pid_array = static::getAllWorkerPids();
            // Send stop signal to all child processes.
            $sig=static::$_gracefulStop?SIGTERM:SIGINT;
            foreach ($worker_pid_array as $worker_pid) {
                \posix_kill($worker_pid, $sig);
            }

        } // For child processes.
        else {
            // Execute exit.
            foreach (static::$_workers as $worker) {
                if(!$worker->stopping){
                    $worker->stop();
                    $worker->stopping = true;
                }
            }
            if (!static::$_gracefulStop || ConnectionInterface::$statistics['connection_count'] <= 0) {
                static::$_workers = array();
                if (static::$globalEvent) {
                    static::$globalEvent->destroy();
                }
                exit(0);
            }
        }
    }
    /**
     * Stop current worker instance.
     *
     * @return void
     */
    public function stop()
    {
        // Try to emit onWorkerStop callback.
        if ($this->onWorkerStop) {
            try {
                \call_user_func($this->onWorkerStop, $this);
            } catch (\Exception $e) {
                static::log($e);
                exit(250);
            } catch (\Error $e) {
                static::log($e);
                exit(250);
            }
        }
        // Remove listener for server socket.
        $this->unlisten();
        // Close all connections for the worker.
        if (!static::$_gracefulStop) {
            foreach ($this->connections as $connection) {
                $connection->close();
            }
        }
        // Clear callback.
        $this->onMessage = $this->onClose = $this->onError = $this->onBufferDrain = $this->onBufferFull = null;
    }
    /**
     * Unlisten.
     *
     * @return void
     */
    public function unlisten() {

    }
    /**
     * check if child processes is really running
     */
    public static function checkIfChildRunning()
    {
        foreach (static::$_pidMap as $worker_id => $worker_pid_array) {
            foreach ($worker_pid_array as $pid => $worker_pid) {
                if (!\posix_kill($pid, 0)) {
                    unset(static::$_pidMap[$worker_id][$pid]);
                }
            }
        }
    }
    /**
     * Get all pids of worker processes.
     *
     * @return array
     */
    protected static function getAllWorkerPids()
    {
        $pid_array = array();
        foreach (static::$_pidMap as $worker_pid_array) {
            foreach ($worker_pid_array as $worker_pid) {
                $pid_array[$worker_pid] = $worker_pid;
            }
        }
        return $pid_array;
    }
    /**
     * Save pid.
     *
     * @throws Exception
     */
    protected static function saveMasterPid()
    {

        static::$_masterPid = \posix_getpid();
        if (false === \file_put_contents(static::$pidFile, static::$_masterPid)) {
            throw new Exception('can not save pid to ' . static::$pidFile);
        }
    }
    protected static function parseCommand(){
        global $argv;
        $start_file = $argv[0];
        $available_commands = array(
            'start',
            'stop',
            'restart',
            'reload',
            'status',
            'connections',
        );
        $usage = "Usage: php yourfile <command> [mode]\nCommands: \nstart\t\tStart worker in DEBUG mode.\n\t\tUse mode -d to start in DAEMON mode.\nstop\t\tStop worker.\n\t\tUse mode -g to stop gracefully.\nrestart\t\tRestart workers.\n\t\tUse mode -d to start in DAEMON mode.\n\t\tUse mode -g to stop gracefully.\nreload\t\tReload codes.\n\t\tUse mode -g to reload gracefully.\nstatus\t\tGet worker status.\n\t\tUse mode -d to show live status.\nconnections\tGet worker connections.\n";
        if (!isset($argv[1]) || !\in_array($argv[1], $available_commands)) {
            if (isset($argv[1])) {
                static::safeEcho('Unknown command: ' . $argv[1] . "\n");
            }
            exit($usage);
        }
        $command=trim($argv[1]);
        $command2=isset($argv[2])?$argv[2]:'';
        // Get master process PID.
        $master_pid      = \is_file(static::$pidFile) ? \file_get_contents(static::$pidFile) : 0;
        $master_is_alive = $master_pid && \posix_kill($master_pid, 0) && \posix_getpid() !== $master_pid;
        // Master is still alive?
        if ($master_is_alive) {
            if ($command === 'start') {
                static::log("$start_file already running");
                exit;
            }
        } elseif ($command !== 'start' && $command !== 'restart') {
            static::log("$start_file not run");
            exit;
        }
        switch ($command){
            case 'start':{
                if($command2 ==='-d'){
                    static::$daemonize=true;
                }
                break;
            }
            case 'stop':
                if ($command2 === '-g') {
                    static::$_gracefulStop = true;
                    $sig = SIGTERM;
                    static::log("$start_file is gracefully stopping ...");
                } else {
                    static::$_gracefulStop = false;
                    $sig = SIGINT;
                    static::log("Workerman[$start_file] is stopping ...");
                }
                // Send stop signal to master process.
                $master_pid && \posix_kill($master_pid, $sig);
                // Timeout.
                $timeout    = 5;
                $start_time = \time();
                //检查master是否还在活着
                while (1) {
                    $master_is_alive = $master_pid && \posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        // Timeout?
                        if (!static::$_gracefulStop && \time() - $start_time >= $timeout) {
                            static::log("$start_file stop fail");
                            exit;
                        }
                        \usleep(10000);
                        continue;
                    }
                    // Stop success.
                    static::log("$start_file stop success");
                    if ($command === 'stop') {
                        exit(0);
                    }
                    if ($command2 === '-d') {
                        static::$daemonize = true;
                    }
                    break;
                }
                break;
            case 'reload':
                if($command2 === '-g'){
                    $sig = SIGQUIT;
                }else{
                    $sig = SIGUSR1;
                }
                static::log("sig=".$sig."-".basename(__FILE__).__LINE__);
                \posix_kill($master_pid, $sig);
                exit();

        }

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
    private static function outputStream($stream = null)
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

}




