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
       static::installSignal();
        static::init();
        static::forkWorkers();
        static::wait();
    }
    protected static function installSignal() {
        pcntl_async_signals(true);
        pcntl_signal(SIGINT, [self::class, 'signalHandler'], false);
        pcntl_signal(SIGUSR1, [self::class, 'signalHandler'], false);
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
        echo posix_getpid().PHP_EOL;
        while(count(static::$_pidMap[$worker->workerId])<$worker->count){
            $id=static::getId($worker->workerId,0);
            if($id===false){
                return; //说明已经满了
            }
            $pid = pcntl_fork();
            if ($pid < 0){
                die("fork err!");
            }
            //parent
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
        echo posix_getpid()." receive " . $signo . PHP_EOL;
        if ($signo == SIGINT) {
            exit();
        }elseif ($signo==SIGUSR1){
            print_r(static::$_pidMap);
            print_r(static::$_idMap);
        }
    }
}




