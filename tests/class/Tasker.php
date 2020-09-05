<?php
namespace tests;

use tasker\exception\Exception;
use tasker\Op;

class Tasker
{
    public function test($param1,$param2){
        Op::sleep(0.1);
        if(mt_rand(0,10)<4)
        {
            return false;
        }
        if(mt_rand(0,10)<2)
        {
            throw new Exception('test');
        }

    }
}