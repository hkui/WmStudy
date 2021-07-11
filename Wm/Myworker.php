<?php
namespace Wm;

require_once __DIR__ . '/Lib/Constants.php';

use Wm\Connection\ConnectionInterface;
use Wm\Lib\Timer;
use Wm\Events\EventInterface;

use Exception;

class Myworker extends MyworkerBase
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
     * Name of the worker processes.
     *
     * @var string
     */
    public $name = 'none';

    /**
     * All worker instances.
     *
     * @var Myworker[]
     */
    public static $_workers = [];

    /**
     * All worker processes pid.
     * The format is like this [worker_id=>[pid=>pid, pid=>pid, ..], ..]
     *
     * @var array
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
    public static $pidFile = '';


    public static $_gracefulStop;
    protected static $_masterPid = 0;
    /**
     * Emitted when the master process get reload signal.
     *
     * @var callable
     */
    public static $onMasterReload = null;
    /**
     * Emitted when the master process terminated.
     *
     * @var callable
     */
    public static $onMasterStop = null;
    /**
     * Socket name. The format is like this http://0.0.0.0:80 .
     *
     * @var string
     */
    protected $_socketName = '';

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
    /**
     * After sending the restart command to the child process KILL_WORKER_TIMER_TIME seconds,
     * if the process is still living then forced to kill.
     *
     * @var int
     */
    const KILL_WORKER_TIMER_TIME = 2;
    /**
     * EventLoopClass
     *
     * @var string
     */
    public static $eventLoopClass = '';

    /**
     * Available event loops.
     *
     * @var array
     */
    protected static $_availableEventLoops = array(
        'libevent' => '\Wm\Events\Libevent',
        'event'    => '\Wm\Events\Event'
        // Temporarily removed swoole because it is not stable enough
        //'swoole'   => '\Workerman\Events\Swoole'
    );
    /**
     * Root path for autoload.
     *
     * @var string
     */
    protected $_autoloadRootPath = '';

    /**
     * reloadable.
     *
     * @var bool
     */
    public $reloadable = true;
    /**
     * Transport layer protocol.
     *
     * @var string
     */
    public $transport = 'tcp';

    public function __construct()
    {
        $this->workerId                    = \spl_object_hash($this);
        static::$_workers[$this->workerId] = $this;
        static::$_pidMap[$this->workerId]  = [];
        $backtrace                = \debug_backtrace();
        $this->_autoloadRootPath = \dirname($backtrace[0]['file']);
    }

    public static function runAll()
    {
        static::init();
        static::parseCommand();
        static::installSignal();
        echo __LINE__."  pid=".posix_getpid().PHP_EOL;
        static::resetStd();
        static::daemonize();
        echo __LINE__." pid=".posix_getpid().PHP_EOL;
        static::saveMasterPid();
        echo __LINE__." pid=".posix_getpid().PHP_EOL;
        static::forkWorkers();
        static::monitorWorkers();
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
        //当前php正在运行的进程
        if($pid>0){
            echo "【".__LINE__."】 pid=".posix_getpid(). "  exit!  fork son pid=".$pid.PHP_EOL;
            exit();
        }
        //pid=0
        //son 设置会话组，成为组长
        if(posix_setsid()<0){
            throw  new  \Exception("setsid err");
        }
        //子进程再次fork,产生子进程，作为之后的master进程
        $pid= pcntl_fork();
        if($pid<0){
            throw  new  \Exception("fork err");
        }
        //当前的子进程退出
        if($pid>0){
            echo  "【".__LINE__."】 pid=".posix_getpid(). "  exit!  fork son pid=".$pid.PHP_EOL;
            exit();
        }
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
            static::$logFile = realpath(__DIR__ . '/../wm.log');
        }
        $log_file = (string)static::$logFile;
        if (!\is_file($log_file)) {
            \touch($log_file);
            \chmod($log_file, 0622);
        }
        // State.
        static::$_status = static::STATUS_STARTING;

        // Process title.
        static::setProcessTitle('Wm: master process  start_file=' . static::$_startFile);

        // Init data for worker id.
        static::initId();

        // Timer init.
        Timer::init();
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


    public static function monitorWorkers()
    {
        static::$_status = static::STATUS_RUNNING;

        while(true){
            $status=0;
            $pid = pcntl_wait($status, WUNTRACED);
            echo "waitPid=".$pid.PHP_EOL;
            if($pid>0){
                foreach (static::$_pidMap as  $worker_id=>$worker_pid_array){
                    if(!isset($worker_pid_array[$pid])){
                        continue;
                    }
                    $worker = static::$_workers[$worker_id];
                    // Exit status.
                    if ($status !== 0) {
                        static::log("worker[" . $worker->name . ":$pid] exit with status $status ");
                        static::log("worker[" . $worker->name . ":$pid] exit with status $status ".pcntl_wifsignaled($status)."---".pcntl_wtermsig($status));

                    }
                    // Clear process data.
                    unset(static::$_pidMap[$worker_id][$pid]);

                    // Mark id is available.
                    $id                              = static::getId($worker_id, $pid);
                    static::$_idMap[$worker_id][$id] = 0;

                    break;
                }
                // Is still running state then fork a new worker process.
                if (static::$_status !== static::STATUS_SHUTDOWN) {
                    static::forkWorkers();
                    // If reloading continue.
                    if (isset(static::$_pidsToRestart[$pid])) {
                        unset(static::$_pidsToRestart[$pid]);
                        static::reload();
                    }
                }

            }
            // If shutdown state and all child processes exited then master process exit.
            if (static::$_status === static::STATUS_SHUTDOWN && !static::getAllWorkerPids()) {
                static::exitAndClearAll();
            }
        }
    }
    /**
     * Exit current process.
     *
     * @return void
     */
    protected static function exitAndClearAll()
    {
        foreach (static::$_workers as $worker) {
            $socket_name = $worker->getSocketName();
            if ($worker->transport === 'unix' && $socket_name) {
                list(, $address) = \explode(':', $socket_name, 2);
                @\unlink($address);
            }
        }
        @\unlink(static::$pidFile);
        static::log("Wm[" . \basename(static::$_startFile) . "] has been stopped");
        if (static::$onMasterStop) {
            \call_user_func(static::$onMasterStop);
        }
        exit(0);
    }
    /**
     * Get socket name.
     *
     * @return string
     */
    public function getSocketName()
    {
        return $this->_socketName ? lcfirst($this->_socketName) : 'none';
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
                if (static::$_status === static::STATUS_STARTING) {
//                    static::resetStd();
                }
                $worker->run();
            }
        }
    }
    /**
     * Run worker instance.
     *
     * @return void
     */
    public function run()
    {
        //Update process state.
        static::$_status = static::STATUS_RUNNING;

        // Register shutdown function for checking errors.
        \register_shutdown_function(array("\\Wm\\Myworker", 'checkErrors'));

        // Set autoload root path.
        Autoloader::setRootPath($this->_autoloadRootPath);

        $i = 0;
        while (1) {
            echo "[".date("H:i:s")."]--".$i . "--" . posix_getpid() . PHP_EOL;
            sleep(3);
            $i++;
        }
        exit();

        // Create a global event loop.
        if (!static::$globalEvent) {
            $event_loop_class = static::getEventLoopName();
            static::$globalEvent = new $event_loop_class;
            $this->resumeAccept();
        }

        // Reinstall signal.
        static::reinstallSignal();

        // Init Timer.
        Timer::init(static::$globalEvent);

        // Set an empty onMessage callback.
        if (empty($this->onMessage)) {
            $this->onMessage = function () {};
        }

        \restore_error_handler();

        // Try to emit onWorkerStart callback.
        if ($this->onWorkerStart) {
            try {
                \call_user_func($this->onWorkerStart, $this);
            } catch (\Exception $e) {
                static::log($e);
                // Avoid rapid infinite loop exit.
                sleep(1);
                exit(250);
            } catch (\Error $e) {
                static::log($e);
                // Avoid rapid infinite loop exit.
                sleep(1);
                exit(250);
            }
        }

        // Main loop.
        static::$globalEvent->loop();
    }
    /**
     * Reinstall signal handler.
     *
     * @return void
     */
    protected static function reinstallSignal()
    {
        // uninstall stop signal handler
        \pcntl_signal(SIGINT, SIG_IGN, false);
        // uninstall graceful stop signal handler
        \pcntl_signal(SIGTERM, SIG_IGN, false);
        // uninstall reload signal handler
        \pcntl_signal(SIGUSR1, SIG_IGN, false);
        // uninstall graceful reload signal handler
        \pcntl_signal(SIGQUIT, SIG_IGN, false);
        // uninstall status signal handler
        \pcntl_signal(SIGUSR2, SIG_IGN, false);
        // uninstall connections status signal handler
        \pcntl_signal(SIGIO, SIG_IGN, false);
        // reinstall stop signal handler
        static::$globalEvent->add(SIGINT, EventInterface::EV_SIGNAL, array('\Wm\Myworker', 'signalHandler'));
        // reinstall graceful stop signal handler
        static::$globalEvent->add(SIGTERM, EventInterface::EV_SIGNAL, array('\Wm\Myworker', 'signalHandler'));
        // reinstall reload signal handler
        static::$globalEvent->add(SIGUSR1, EventInterface::EV_SIGNAL, array('\Wm\Myworker', 'signalHandler'));
        // reinstall graceful reload signal handler
        static::$globalEvent->add(SIGQUIT, EventInterface::EV_SIGNAL, array('\Wm\Myworker', 'signalHandler'));
        // reinstall status signal handler
        static::$globalEvent->add(SIGUSR2, EventInterface::EV_SIGNAL, array('\Wm\Myworker', 'signalHandler'));
        // reinstall connection status signal handler
        static::$globalEvent->add(SIGIO, EventInterface::EV_SIGNAL, array('\Wm\Myworker', 'signalHandler'));
    }
    /**
     * Resume accept new connections.
     *
     * @return void
     */
    public function resumeAccept()
    {
        return ;
        // Register a listener to be notified when server socket is ready to read.
        if (static::$globalEvent && true === $this->_pauseAccept && $this->_mainSocket) {
            if ($this->transport !== 'udp') {
                static::$globalEvent->add($this->_mainSocket, EventInterface::EV_READ, array($this, 'acceptConnection'));
            } else {
                static::$globalEvent->add($this->_mainSocket, EventInterface::EV_READ, array($this, 'acceptUdpConnection'));
            }
            $this->_pauseAccept = false;
        }
    }
    /**
     * Get event loop name.
     *
     * @return string
     */
    protected static function getEventLoopName()
    {
        if (static::$eventLoopClass) {
            return static::$eventLoopClass;
        }

        if (!\class_exists('\Swoole\Event', false)) {
            unset(static::$_availableEventLoops['swoole']);
        }

        $loop_name = '';
        foreach (static::$_availableEventLoops as $name=>$class) {
            if (\extension_loaded($name)) {
                $loop_name = $name;
                break;
            }
        }

        if ($loop_name) {
            if (\interface_exists('\React\EventLoop\LoopInterface')) {
                switch ($loop_name) {
                    case 'libevent':
                        static::$eventLoopClass = '\Wm\Events\React\ExtLibEventLoop';
                        break;
                    case 'event':
                        static::$eventLoopClass = '\Wm\Events\React\ExtEventLoop';
                        break;
                    default :
                        static::$eventLoopClass = '\Wm\Events\React\StreamSelectLoop';
                        break;
                }
            } else {
                static::$eventLoopClass = static::$_availableEventLoops[$loop_name];
            }
        } else {
            static::$eventLoopClass = \interface_exists('\React\EventLoop\LoopInterface') ? '\Wm\Events\React\StreamSelectLoop' : '\Wm\Events\Select';
        }
        return static::$eventLoopClass;
    }



    public static function signalHandler($signal)
    {
        $ismaster=static::$_masterPid==posix_getpid()?true:false;
        if($ismaster){
            $str="master";
        }else{
            $str='';
        }
        static::log("line=".__LINE__."  {$str}  get signal=".$signal." pid=".posix_getpid());

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
                static::$_gracefulStop=$signal === SIGQUIT?true:false;
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
    /**
     * Execute reload.
     *
     * @return void
     */
    protected static function reload()
    {
        // For master process.
        if (static::$_masterPid === \posix_getpid()) {
            // Set reloading state.
            if (static::$_status !== static::STATUS_RELOADING && static::$_status !== static::STATUS_SHUTDOWN) {
                static::log("Wm[" . \basename(static::$_startFile) . "] reloading");
                static::$_status = static::STATUS_RELOADING;
                // Try to emit onMasterReload callback.
                if (static::$onMasterReload) {
                    try {
                        \call_user_func(static::$onMasterReload);
                    } catch (\Exception $e) {
                        static::log($e);
                        exit(250);
                    } catch (\Error $e) {
                        static::log($e);
                        exit(250);
                    }
                    static::initId();
                }
            }

            if (static::$_gracefulStop) {
                $sig = SIGQUIT;
            } else {
                $sig = SIGUSR1;
            }

            // Send reload signal to all child processes.
            $reloadable_pid_array = array();
            foreach (static::$_pidMap as $worker_id => $worker_pid_array) {
                $worker = static::$_workers[$worker_id];
                if ($worker->reloadable) {
                    foreach ($worker_pid_array as $pid) {
                        $reloadable_pid_array[$pid] = $pid;
                    }
                } else {
                    foreach ($worker_pid_array as $pid) {
                        // Send reload signal to a worker process which reloadable is false.
                        \posix_kill($pid, $sig);
                    }
                }
            }
            echo "reloadAble=".join(',',$reloadable_pid_array)." pid=".posix_getpid().PHP_EOL;
            // Get all pids that are waiting reload.
            static::$_pidsToRestart = \array_intersect(static::$_pidsToRestart, $reloadable_pid_array);

            // Reload complete.
            if (empty(static::$_pidsToRestart)) {
                if (static::$_status !== static::STATUS_SHUTDOWN) {
                    static::$_status = static::STATUS_RUNNING;
                }
                return;
            }
            // Continue reload.
            $one_worker_pid = \current(static::$_pidsToRestart);
            // Send reload signal to a worker process.
            \posix_kill($one_worker_pid, $sig);
            // If the process does not exit after static::KILL_WORKER_TIMER_TIME seconds try to kill it.
            if(!static::$_gracefulStop){
                Timer::add(static::KILL_WORKER_TIMER_TIME, '\posix_kill', array($one_worker_pid, SIGKILL), false);
            }
            static::log("line=".__LINE__."  master reload pid=".posix_getpid());
        }else {
            // For child processes.
            static::log("line=".__LINE__." worker reload pid=".posix_getpid());
            \reset(static::$_workers);
            $worker = \current(static::$_workers);
            // Try to emit onWorkerReload callback.
            if ($worker->onWorkerReload) {
                try {
                    \call_user_func($worker->onWorkerReload, $worker);
                } catch (\Exception $e) {
                    static::log($e);
                    exit(250);
                } catch (\Error $e) {
                    static::log($e);
                    exit(250);
                }
            }
            if ($worker->reloadable) {
                static::stopAll();
            }
        }
    }

    public static function stopAll()
    {
        static::$_status = static::STATUS_SHUTDOWN;
        // For master process.
        if (static::$_masterPid === \posix_getpid()) {
            static::log(__LINE__." stopping ...");
            $worker_pid_array = static::getAllWorkerPids();
            // Send stop signal to all child processes.
            $sig=static::$_gracefulStop?SIGTERM:SIGINT;
            foreach ($worker_pid_array as $worker_pid) {
                \posix_kill($worker_pid, $sig);
                if(!static::$_gracefulStop){
                    Timer::add(static::KILL_WORKER_TIMER_TIME, '\posix_kill', array($worker_pid, SIGKILL), false);
                }
            }
            Timer::add(1, "\\Wm\\Myworker::checkIfChildRunning");

        }else {
            // For child processes.
            // Execute exit.
            static::log(__LINE__." worker stopping ....");

            foreach (static::$_workers as $worker) {
                if(!$worker->stopping){
                    $worker->stop();
                    $worker->stopping = true;
                }
            }
            //不是优雅的退出的
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
                        static::log("master is  alive");
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
                static::log("sig=".$sig."-".basename(__FILE__)." ".__LINE__);
                \posix_kill($master_pid, $sig);
                exit();
        }
    }
}
