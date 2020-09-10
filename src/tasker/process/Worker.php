<?php


namespace tasker\process;


use tasker\Console;
use tasker\exception\ClassNotFoundException;
use tasker\exception\DatabaseException;
use tasker\exception\Exception;
use tasker\Op;
use tasker\queue\Database;
use tasker\exception\RetryException;
use tasker\queue\Redis;
use ReflectionClass;

class Worker extends Process
{
    protected $cfg;
    private $_status=[];
    private $_start_time;
    public function __construct($cfg)
    {
        $this->_start_time=time();
        $this->cfg=$cfg;
        $this->_initStatus();
        $this->setProcessTitle($this->cfg['worker_title']);
        $this->_process_id = posix_getpid();
        Console::log("worker ".$this->_process_id." start success");
        $this->installSignal();
        if($this->cfg['tasker_user'])
        {
            $this->setUser($this->cfg['tasker_user']);
        }
    }

    private function _initStatus()
    {
        $redis=Redis::getInstance($this->cfg['redis']);
        $res=$redis->lpop($this->cfg['redis']['queue_key'].'_reload_status');
        $load_status=$res?unserialize($res):[];
        if($load_status)
        {
            $this->_status=$load_status;
        }
        else {
            $this->_status['start_memory'] = memory_get_usage();
            $this->_status['start_time'] = Op::microtime();
            $this->_status['success_count'] = 0;
            $this->_status['fail_count'] = 0;
            $this->_status['except_count'] = 0;
            $this->_status['fast_speed'] = null;
            $this->_status['slow_speed'] = null;
            $this->_status['work_time'] = 0;
        }
    }
    public function run(){
        try{
            $this->whileWorking();
            return;
        }
        catch (DatabaseException $e)
        {
            Console::log($e->getMessage().'['.$e->getSql().']');
        }
        catch(\Throwable $e){
        }
        catch(\Exception $e)
        {
        }
        if(!empty($e))
        {
            $this->saveStatusReload("worker exception ".posix_getpid()." : ".$e->getMessage());
        }
    }
    public function whileWorking(){
        while (1) {
            // 捕获信号
            pcntl_signal_dispatch();
            $cfg=$this->cfg;
            $redis=Redis::getInstance($cfg['redis']);
            $db=Database::getInstance($cfg['database']);
            $taster=$redis->lpop($cfg['redis']['queue_key']);
            if($taster && $taster=unserialize($taster))
            {
                $start=Op::microtime();
                $db->beginTransaction();
                try{
                    $jobs=$db->query('select id,payload,dotimes from ' . $cfg['database']['table'] .
                        ' where id='.$taster['id'].' limit 1');
                    $job=$jobs[0];
                    if($job['dotimes']>=$cfg['retry_count'] )
                    {
                        $db->exce('update ' .
                            $cfg['database']['table'] . ' set startat=0 where id ='.$taster['id']);
                    }
                    else{
                        $payload=json_decode($taster['payload'],true);
                        //在判断一次
                        if(!class_exists($payload[0]))
                        {
                            throw new ClassNotFoundException($payload[0]);
                        }
                        $class=new ReflectionClass($payload[0]);
                        if(!method_exists($payload[0],$payload[1]) && !method_exists($payload[0],'__call'))
                        {
                            throw new ClassNotFoundException($payload[0],$payload[1]);
                        }
                        if(!method_exists($payload[0],$payload[1]))
                        {
                            //调用__call墨书方法
                            $callback=[(new $payload[0]),$payload[1]];
                        }
                        else{
                            if($class->getMethod($payload[1])->isStatic())
                            {
                                //静态方法调用
                                $callback=[ $payload[0],$payload[1]];
                            }
                            else{
                                $callback=[(new $payload[0]),$payload[1]];
                            }
                        }
                        if(false===call_user_func($callback,...$payload[2]))
                        {
                            throw new RetryException(serialize($taster));
                        }
                        //任务标记为成功
                        $db->exce('update ' . $cfg['database']['table'] .
                            ' set endat=' . time() . ' where id=' . $taster['id']);
                    }
                    $db->commit();
                    $this->_status['success_count']++;
                }
                catch (\Throwable $e)
                {
                    //php高版本抛出error
                }
                catch (\Exception $e)
                {
                    //不支持Throwable的低版本
                } finally {
                    if(!empty($e))
                    {
                        $db->rollBack();
                        if($e instanceof RetryException)
                        {
                            //重新放入队列
                            if($db->exce('update ' .
                                $cfg['database']['table'] . ' set
                            dotimes=dotimes+1 where dotimes<10 and id ='.$taster['id'])){
                                $redis->lpush($cfg['redis']['queue_key'],$e->getMessage());
                            }
                            $this->_status['fail_count']++;
                        }
                        else{
                            //记录异常
                            $db->exce('update ' . $cfg['database']['table'] . ' set startat=0,dotimes=99, exception="' . addslashes($e->getMessage()) . '" where id=' . $taster['id']);
                            $this->_status['except_count']++;
                        }
                        unset($e);
                    }
                    $use=Op::microtime()-$start;
                    if(is_null($this->_status['slow_speed']) || $use>$this->_status['slow_speed'])
                    {
                        $this->_status['slow_speed']=$use;
                    }
                    if(is_null($this->_status['fast_speed']) || $use<$this->_status['fast_speed'])
                    {
                        $this->_status['fast_speed']=$use;
                    }
                    $this->_status['work_time']+=$use;
                }
            }
            else{
                //休息0.1秒 防止cpu常用
                $cd=0.1;
                Op::sleep($cd);
                if($this->cfg['workering_time']>0 && time()-$this->_start_time>$this->cfg['workering_time'])
                {
                    $this->saveStatusReload("worked ".$this->cfg['workering_time']."s reload worker");
                }
                if(!is_null($this->cfg['keep_workering_callback']))
                {
                    if($this->cfg['keep_workering_ping_interval']>0)
                    {
                        static $last_keep=0;
                        if(time()-$last_keep>$this->cfg['keep_workering_ping_interval'])
                        {
                            $last_keep=time();
                            $class=new ReflectionClass($this->cfg['keep_workering_callback'][0]);
                            if($class->getMethod($this->cfg['keep_workering_callback'][1])->isStatic())
                            {
                                //静态方法调用
                                $callback=[ $this->cfg['keep_workering_callback'][0],$this->cfg['keep_workering_callback'][1]];
                            }
                            else{
                                $callback=[(new $this->cfg['keep_workering_callback'][0]),$this->cfg['keep_workering_callback'][1]];
                            }
                            call_user_func($callback);
                        }
                    }
                }
                if(false===$db->ping())
                {
                    Database::free();
                }
            }
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
        // SIGUSR2
        pcntl_signal(SIGUSR2, array($this, 'signalHandler'), false);


        // 忽略信号
        pcntl_signal(SIGHUP, SIG_IGN, false);
        pcntl_signal(SIGPIPE, SIG_IGN, false);
        pcntl_signal(SIGQUIT, SIG_IGN, false);
        pcntl_signal(SIGCHLD, SIG_IGN, false);
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
            case SIGUSR1:
                $this->status();
                break;
            case SIGUSR2:
                $this->saveStatusReload("worker hot update reload stop");
                break;
            default:
                break;
        }
    }
    protected function saveStatusReload($msg){
        Redis::getInstance($this->cfg['redis'])->lpush($this->cfg['redis']['queue_key'].'_reload_status',serialize($this->_status));
        Console::log($msg);
        exit(0);
    }
    protected function stop()
    {
        Console::log("worker process is stop");
        exit(0);
    }
    protected function status(){
        //统计状态存放到文件
        $_now_time=Op::microtime();
        $process_id=$this->_process_id;
        $memory=Op::memory2M( memory_get_usage()-$this->_status['start_memory']);
        //运行了多少时间
        $runtime=Op::dtime($_now_time-$this->_status['start_time']);
        //最快时间
        $fast_speed=$this->_status['fast_speed']?round(1/$this->_status['fast_speed'],2):0;
        $slow_speed=$this->_status['slow_speed']?round(1/$this->_status['slow_speed'],2):0;
        $success_count=$this->_status['success_count'];
        $fail_count=$this->_status['fail_count'];
        $except_count=$this->_status['except_count'];
        $complete_count=$success_count+$fail_count+$except_count;
        $work_time=Op::dtime($this->_status['work_time']);
        $agv_speed=$complete_count>0?round($complete_count/$this->_status['work_time'],2):0;
        $sleep_time=Op::dtime($_now_time-$this->_status['start_time']-$this->_status['work_time']);
        $data=compact(
            'process_id',
            'memory',
                'runtime',
                'sleep_time',
                'fail_count',
                'success_count',
                'except_count',
                'fast_speed',
                'slow_speed',
                'agv_speed',
                'work_time'
            );
        Redis::getInstance($this->cfg['redis'])->lpush($this->cfg['redis']['queue_key'].'_status_data',serialize($data));
    }

}