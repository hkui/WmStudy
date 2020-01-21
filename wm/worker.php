<?php


class worker
{
    public $worker_num=2;
    public $workers=[];
    public $master_pid;
    public function __construct()
    {

    }



    public function run(){
        $this->master_pid=posix_getpid();
        echo $this->master_pid.PHP_EOL;
        pcntl_async_signals(true);
        pcntl_signal(SIGINT,[$this,'signalHandler'],false);

        for ($i=0;$i<$this->worker_num;$i++){
            $pid=pcntl_fork();
            if($pid<0) die("fork err!");
            if ($pid>0){
                $this->workers[]=$pid;
            }else{
                //son
                $i=0;

                while(1){
                    echo $i."--".posix_getpid().PHP_EOL;
                    sleep(1);
                    $i++;
                }
                exit();
            }
        }

        while(true){
            $waitpid=pcntl_wait($status,WUNTRACED);
            if($waitpid==-1){
                break;
            }
            if($waitpid){
                unset($this->workers[$waitpid]);
                echo $waitpid."    exit !".PHP_EOL;
                if(empty($this->workers)){
                    break;
                }
            }else{
                echo $waitpid.PHP_EOL;

            }

        }







    }
    public function signalHandler($signo){
        if($signo==SIGINT){
            if(posix_getpid()==$this->master_pid){
                echo "master receive ".$signo.PHP_EOL;
            }else{
                exit(1);
            }
        }
    }


}

$w=new worker();
$w->worker_num=2;
$w->run();


