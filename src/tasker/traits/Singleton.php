<?php


namespace tasker\traits;


trait Singleton
{
    private static $instance;

    public static function getInstance(...$args)
    {
        if (!self::$instance instanceof self) {
            self::$instance = new self(...$args);
        }
        return self::$instance;
    }

    public static function free()
    {
        self::$instance = null;
    }
}