<?php
namespace tests;

use tasker\exception\Exception;
use tasker\Op;
use tasker\Tasker;

class Tasker_test
{
    public function test(){
        Op::sleep(0.1);
        if(mt_rand(0,10)<2)
        {
            return false;
        }
        if(mt_rand(0,10)<1)
        {
            throw new Exception('test');
        }
    }
    public function long_test(){
        Op::sleep(0.1);
        Tasker::push(__CLASS__,'long_test');
        Tasker::push(__CLASS__,'test',[],null,true);

    }
    //程序自动判断动态静态方法
    public function keep(){
        //保持链接的操作
    }
    public static function skeep(){
        //保持链接的操作
    }
}
