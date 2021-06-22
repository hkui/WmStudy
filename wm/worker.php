<?php

class WWorker
{
    public $count = 2;

    public static $_workers = [];
    public static $_pidMap=[];
    protected static $_idMap = array();
    public $workerId;

    public function __construct()
    {
        $this->workerId                    = \spl_object_hash($this);
        static::$_workers[$this->workerId] = $this;
        static::$_pidMap[$this->workerId]  = [];
    }

    public static function run()
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [self::class, 'signalHandler'], false);

        self::fork();
        self::wait();
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
            if ($waitpid == -1) {
                break;
            }
            if ($waitpid) {
                unset(self::$_workers[$worker->workerId][$waitpid]);
                echo $waitpid . "    exit !" . PHP_EOL;
                if (empty(self::$workers)) {
                    break;
                }
            } else {
                echo $waitpid . PHP_EOL;
            }
        }
    }

    public static function fork()
    {
        foreach (self::$_workers as $worker){
            self::forkOne($worker);
        }

    }

    /**
     * @param $worker self
     */
    public static function forkOne($worker){
        for ($i = 0; $i < $worker->count; $i++) {
            $pid = pcntl_fork();
            if ($pid < 0){
                die("fork err!");
            }
            if ($pid > 0) {
                self::$_pidMap[$worker->workerId][$pid]=$pid;
                //self::$_idMap[$worker->workerId][$pid]=$id;
            } else {
                //son
                $i = 0;
                while (1) {
                    echo $i . "--" . posix_getpid() . PHP_EOL;
                    sleep(2);
                    $i++;
                }
                exit();
            }
        }
    }
    public static function signalHandler($signo)
    {
        if ($signo == SIGINT) {
            echo posix_getpid()." receive " . $signo . PHP_EOL;
            exit();
        }
    }
}

$w = new WWorker();
$w->count = 3;
WWorker::run();


