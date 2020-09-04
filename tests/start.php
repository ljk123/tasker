<?php
require_once dirname(__FILE__).'/../vendor/autoload.php';

use tasker\Tasker;
Tasker::run([
    //传入配置
    'worker_nums'=>1,
    'hot_update_path'=>[
        dirname(__FILE__)
    ],
    'redis'=>[
        'host'=>'127.0.0.1',
        'port'=>6379,
        'db'=>8,
        'pwd'=>'ljk2fxf',
        'queue_key'=>'task'
    ],
    'database'=>[
        'host'=>'127.0.0.1',
        'db'=>'task',
        'user'=>'task',
        'pwd'=>'123456',
        'port'=>3306,
        'table'=>'task',
        'charset'=>'utf8'
    ],
]);
