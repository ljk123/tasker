<?php


namespace tasker\exception;



class RedisException extends Exception
{
    public function __construct($message = "" )
    {
        $path=$this->getFile().':'.$this->getLine();
        $this->message="[RedisException]$message at $path";
    }
}