<?php


namespace tasker;
/**
 * Class Op
 * 一些优化函数
 * @package tasker
 */
class Op
{
    /**
     * 优化sleep 调用usleep 参数为秒
     * @param $sec
     */
    public static function sleep($sec)
    {
        usleep(intval(floatval($sec) * 1000000));
    }

    public static function dtime($time)
    {
        $d = floor($time / 86400);//天
        $h = floor(($time - $d * 86400) / 3600);//小时
        $m = floor(($time - $d * 86400 - $h * 3600) / 60);//分
        $s = $time % 60;
        return sprintf("%dD %d:%d:%d", $d, $h, $m, $s);
    }

    public static function microtime()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    public static function memory2M($memory)
    {
        return round($memory / 1024 / 1024, 2) . 'M';
    }
}