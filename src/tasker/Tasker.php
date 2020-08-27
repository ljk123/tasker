<?php


namespace tasker;


use tasker\exception\Exception;
use tasker\process\Master;

class Tasker
{
    const VERSION='1.0';
    const IS_DEBUG=true;
    protected static $is_cli=false;

    /**
     * 守护态运行.
     */
    protected static function daemonize()
    {
        $pid = pcntl_fork();
        if (-1 === $pid) {
            Console::display('process fork fail');
        } elseif ($pid > 0) {
            Console::hearder();
            exit(0);
        }
        //  子进程了
        // 将当前进程提升为会话leader
        if (-1 === posix_setsid()) {
            exit("process setsid fail\n");
        }
        // 再次fork以避免SVR4这种系统终端再一次获取到进程控制
        $pid = pcntl_fork();
        if (-1 === $pid) {
            exit("process fork fail\n");
        } elseif (0 !== $pid) {
            exit(0);
        }
        umask(0);
        chdir('/');
    }


    /**
     * 解析命令参数.
     * @param $cfg
     */
    protected static function parseCmd($cfg)
    {
        global $argv;
        $command = isset($argv[1]) ? $argv[1] : '';

        // 获取master的pid和存活状态
        $masterPid = is_file($cfg['pid_path']) ? file_get_contents($cfg['pid_path']) : 0;
        $masterAlive = $masterPid ? posix_kill($masterPid,0) : false;
        if ($masterAlive) {
            if ($command === 'start') {
                Console::display('Task already running at '.$masterPid);
            }
        } else {
            if ($command && $command !== 'start' && $command !== 'status') {
                Console::display('Task not run');
            }
        }
        switch ($command) {
            case 'start':
                break;
            case 'stop':
            case 'restart':
                Console::display('Task stopping ...',false);
                // 给master发送stop信号
                posix_kill($masterPid, SIGINT);

                $timeout = $cfg['stop_worker_timeout']+1;
                $startTime = time();
                while (posix_kill($masterPid,0)) {
                    usleep(1000);
                    if (time() - $startTime >= $timeout) {
                        Console::display('Task stop fail');
                    }
                }
                Console::display('Task stop success',$command==='stop');
                break;
            case 'reload':
                Console::display('Task reloading ...',false);
                // 给master发送reload信号
                posix_kill($masterPid, SIGUSR1);
                exit(0);

            default:
                $usage = "
Usage: Commands \n\n
Commands:\n
start\t\tStart worker.\n
stop\t\tStop worker.\n
reload\t\tReload codes.\n
status\t\tWorker status.\n\n\n
Use \"--help\" for more information about a command.\n";
                Console::display($usage);
        }

    }

    /**
     * 环境检测.
     * @return void
     * @throws Exception
     */
    protected static function checkEnv()
    {
        // 只能运行在cli模式
        if (php_sapi_name() != "cli") {
            throw new Exception('Task only run in command line mode');
        }
        self::$is_cli=true;
        
    }

    protected static function parseCfg(&$cfg){
        $task_cfg=require dirname(__FILE__).'/../config.php';
        $cfg_key=array_keys($task_cfg);
        foreach ($cfg_key as $key)
        {
            if(!empty($cfg[$key]))
            {
                $task_cfg[$key]=$cfg[$key];
            }
        }
        $cfg=$task_cfg;
    }

    /**
     * 检查一些关键配置
     * @param $cfg
     * @return array
     * @throws Exception
     */
    protected static function checkCfg($cfg){
        if($cfg['worker_nums']<=0)
        {
            if(!self::$is_cli)
            {
                throw new Exception('worker_nums value invalid');
            }
            Console::display('worker_nums value invalid');
        }
        try {
            //检查dababase
//            $res=Database::getInstance($cfg['database'])->query("SHOW COLUMNS FROM ".$cfg['database']['table']);
            //字段检查

            //检查redis
//            Redis::getInstance($cfg['redis'])->ping();
        }
        catch (\Throwable $e)
        {
            if($e instanceof Exception)
            {
                throw $e;
            }
            throw new Exception($e->getMessage());
        }
        return $cfg;

    }


    /**
     * 启动.
     * @param $cfg
     * @throws Exception
     */
    public static function run($cfg)
    {
        self::checkEnv();
        self::parseCfg($cfg);
        self::parseCmd($cfg);
        self::checkCfg($cfg);
        self::daemonize();
        (new Master($cfg))->run();
    }

    /**
     * 添加任务
     * @param array $job_opt 数组 [payload[class,method,data],doat]
     * @param array $cfg
     * @throws Exception
     */
    public static function push($job_opt=[],$cfg=[]){
        if($cfg) {
            //输入配置
            self::cfg($cfg);
        }
        if(empty($cfg))
        {
            throw new Exception('需初始化配置');
        }
//        $job_cfg=$cfg[$cfg['queue_type']];
//        $job_cfg['queue_type']=$cfg['queue_type'];
//        $job_cfg['retry_count']=$cfg['retry_count'];
//        Queue::getInstance($job_cfg)->add(...$job_opt);
    }
    //外部注入配置
    public static function cfg(&$cfg){
        self::parseCfg($cfg);
        self::checkCfg($cfg);
    }

}