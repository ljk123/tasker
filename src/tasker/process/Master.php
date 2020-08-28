<?php


namespace tasker\process;


use tasker\Console;
use tasker\Database;
use tasker\exception\Exception;
use tasker\Provider;
use tasker\Redis;
use tasker\Tasker;
use tasker\traits\Singleton;

class Master extends Process
{
    use Singleton;
    protected $cfg;
    protected $_workers=[];
    public function __construct($cfg)
    {
        $this->cfg=$cfg;
        $this->setProcessTitle($this->cfg['master_title']);
        $this->_process_id = posix_getpid();
    }
    public function getWorkers(){
        return array_values($this->_workers);
    }
    protected function saveMasterPid()
    {
        // 保存pid以实现重载和停止
        if (false === file_put_contents($this->cfg['pid_path'], $this->_process_id)) {
            throw new Exception('can not save pid to'.$this->cfg['pid_path']);
        }
    }
    //安装信号
    protected function installSignal(){
        // SIGINT
        pcntl_signal(SIGINT, array($this, 'signalHandler'), false);
        // SIGTERM
        pcntl_signal(SIGTERM, array($this, 'signalHandler'), false);

        // SIGUSR1
        pcntl_signal(SIGUSR1, array($this, 'signalHandler'), false);
        // SIGQUIT
        pcntl_signal(SIGQUIT, array($this, 'signalHandler'), false);

        // SIGUSR2
        pcntl_signal(SIGUSR2, array($this, 'signalHandler'), false);
        // 忽略信号
        pcntl_signal(SIGHUP, SIG_IGN, false);
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    /**
     * 信号处理器.
     *
     * @param integer $signal 信号.
     */
    protected function signalHandler($signal)
    {
        switch ($signal) {
            case SIGINT:
            case SIGTERM:
                $this->stop();
                break;
            case SIGQUIT:
            case SIGUSR1:
                $this->reload();
                break;
            case SIGUSR2:
                $this->status();
                break;
            default:
                break;
        }
    }

    /**
     * 停止.
     */
    protected function stop()
    {
        // 主进程给所有子进程发送退出信号
        $this->stopAllWorkers();
        if (is_file($this->cfg['pid_path'])) {
            @unlink($this->cfg['pid_path']);
        }
        exit(0);
    }

    /**
     * 重新加载.
     */
    protected function reload()
    {
        // 停止所有worker即可,master会自动fork新worker
        $this->stopAllWorkers();
        $this->checkWorkerAlive();
    }

    /**
     * 获取状态
     */
    protected function status()
    {
        $allWorkerPid = $this->_workers;
        foreach ($allWorkerPid as $workerPid) {
            posix_kill($workerPid, SIGUSR1);
        }


    }
    protected function checkWorkerAlive()
    {
        $allWorkerPid = $this->_workers;
        foreach ($allWorkerPid as $index => $pid) {
            if (!$this->isAlive($pid)) {
                unset($this->_workers[$index]);
            }
        }

        $this->forkWorkers();
    }

    /**
     * 创建所有worker进程.
     * @throws Exception
     */
    protected function forkWorkers()
    {
        while (count($this->_workers) < $this->cfg['worker_nums']) {
            $this->forkOneWorker();
        }
    }

    /**
     * 创建一个worker进程.
     * @throws Exception
     */
    protected function forkOneWorker()
    {
        //创建进程之前释放
        Database::free();
        Redis::free();
        $pid = pcntl_fork();
        // 父进程
        if ($pid > 0) {
            $this->_workers[] = $pid;
        } else if ($pid === 0) { // 子进程
            // 子进程会阻塞在这里
            (new Worker($this->cfg))->run();
            // 子进程退出
            exit(0);
        } else {
            if(!empty($this->_workers))
            {
                $this->stopAllWorkers();
            }
            throw new Exception("fork one worker fail");
        }
    }


    /**
     * 停止所有worker进程.
     */
    protected function stopAllWorkers()
    {
        $allWorkerPid = $this->_workers;
        foreach ($allWorkerPid as $workerPid) {
            posix_kill($workerPid, SIGINT);
        }
        $timeout=$this->cfg['stop_worker_timeout'];
        $status=0;
        $start_time=time();
        while ($this->isAlive($allWorkerPid))
        {
            usleep(1000);
            //这里不检查posix_kill获取不到真是值
            pcntl_wait($status, WNOHANG);
            if(time()-$start_time>$timeout)
            {
                // 子进程退出异常,强制kill
                foreach ($allWorkerPid as $workerPid) {
                    $this->forceKill($workerPid);
                }
                break;
            }
        }
        // 清空worker实例
        $this->_workers= [];
    }
    protected function forceKill($pid)
    {
        // 进程是否存在
        if ($this->isAlive($pid)) {
            posix_kill($pid, SIGKILL);
        }
    }

    /**
     * 进程是否存活.
     *
     * @param mixed $pids 进程pid.
     *
     * @return bool
     */
    protected function isAlive($pids)
    {
        if (!is_array($pids)) {
            $pids = array($pids);
        }

        foreach ($pids as $pid) {
            if (posix_kill($pid, 0)) {
                return true;
            }
        }

        return false;
    }


    /**
     * 关闭标准输出和错误输出.
     */
    protected function resetStdFd()
    {
        if(Tasker::IS_DEBUG)
        {
            return;
        }
        global $STDERR, $STDOUT;

        //重定向标准输出和错误输出
        @fclose(STDOUT);
        fclose(STDERR);
        $STDOUT = fopen($this->cfg['stdout_path'], 'a');
        $STDERR = fopen($this->cfg['stdout_path'], 'a');
    }
    /**
     * master进程监控worker.
     */
    protected function monitor()
    {
        while (1) {
            // 挂起当前进程的执行直到一个子进程退出或接收到一个信号
            $status = 0;
            $pid = pcntl_wait($status, WNOHANG);//WNOHANG 不阻塞
            pcntl_signal_dispatch();
            if ($pid > 0) {
                // worker健康检查
                $this->checkWorkerAlive();
            }
            elseif($pid<0){
                break;
            }
            else{
                //不阻塞的时候 没有子进程退出要做什么写这里
                try{
                    //读取任务丢到list里
                    Provider::moveToList($this->cfg);
                    //休息0.1秒
                    usleep(100000);
                }catch (\Throwable $e)
                {
                    echo  $e->getMessage();
                }
                //扫描监听目录变化 重启worker
            }
            // 其他你想监控的
        }
    }
    public function run(){

        try {
            $this->saveMasterPid();
            $this->forkWorkers();
        } catch (Exception $e) {
            Console::log($e->getMessage(),true);
        }

        $this->installSignal();

        $this->resetStdFd();
        $this->monitor();
    }
}