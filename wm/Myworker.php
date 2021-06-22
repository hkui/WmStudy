<?php

class Myworker
{
    public $count = 2;

    public static $_workers = [];
    /**
     * @var array  [worker_id=>[pid=>pid, pid=>pid, ..], ..]
     */
    public static $_pidMap=[];
    /**
     * @var array [worker_id=>[0=>$pid0, 1=>$pid1, ..], ..].
     */
    protected static $_idMap = array();
    public $workerId;

    public function __construct()
    {
        $this->workerId                    = \spl_object_hash($this);
        static::$_workers[$this->workerId] = $this;
        static::$_pidMap[$this->workerId]  = [];
    }
    protected static function init(){
        static::initId();
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

    public static function run()
    {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [self::class, 'signalHandler'], false);
        static::init();
        static::forkWorkers();
        static::wait();
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
        foreach (self::$_workers as $worker){
            while(count(static::$_pidMap[$worker->workerId])<$worker->count){
                self::forkOne($worker);
            }
        }

    }

    /**
     * @param $worker self
     */
    public static function forkOne($worker){
        $id=static::getId($worker->workerId,0);
        if($id===false){
            return; //说明已经满了
        }
        for ($i = 0; $i < $worker->count; $i++) {
            $pid = pcntl_fork();
            if ($pid < 0){
                die("fork err!");
            }
            if ($pid > 0) {
                self::$_pidMap[$worker->workerId][$pid]=$pid;
                self::$_idMap[$worker->workerId][$id]=$pid;
            } else {
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
    public static function signalHandler($signo)
    {
        if ($signo == SIGINT) {
            echo posix_getpid()." receive " . $signo . PHP_EOL;
            exit();
        }
    }
}

$w = new Myworker();
$w->count = 2;
Myworker::run();


