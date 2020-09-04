<?php


namespace tests;


use tasker\Console;
use tasker\Tasker;

class Gc
{
    //好像解决不了服务器剃掉链接的问题 定时重启进程吧
    private static $_val=null;
    public function gc_test(){
        if(is_null(self::$_val))
        {
            self::$_val=mt_rand(0,10);
        }
    }
    public static function free(){
     self::$_val=null;
    }
}