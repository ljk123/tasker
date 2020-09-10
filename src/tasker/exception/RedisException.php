<?php


namespace tasker\exception;



class RedisException extends Exception
{
    public function __construct($message = "" )
    {
        $this->message="[RedisException]$message";
    }
}