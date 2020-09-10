<?php


namespace tasker\exception;



class DatabaseException extends Exception
{
    protected $sql='';
    public function __construct($message = "", $sql='')
    {
        $this->message=$message;
        $this->sql=$sql;
    }
    public function getSql(){
        return $this->sql;
    }

}