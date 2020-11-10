<?php


namespace tasker\exception;


use Throwable;

class ClassNotFoundException extends Exception
{
    public function __construct($class_name, $method_name = null)
    {
        if (is_null($method_name)) {
            $this->message = "class $class_name not found";
        } else {
            $this->message = "method $method_name not found of $class_name";
        }
    }

}