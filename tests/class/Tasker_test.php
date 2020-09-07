<?php
namespace tests;

use tasker\Console;
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
        if(mt_rand(0,2)==1)
        {
            Tasker::push(__CLASS__,'test');
        }
    }
    public function keep(){
        Console::log('keep');
    }
    public static function skeep(){

        Console::log('skeep');
    }
}