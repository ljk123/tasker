<?php


namespace tasker\process\master;


use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class HotUpdate
{
    protected static $last_mtime;
    protected static $last_check_time = 0;

    public static function check($monitor_dirs, $interval)
    {
        $interval < 0 || $interval = 5;
        if (time() - self::$last_check_time > $interval)//5秒检查一次
        {
            self::$last_check_time = time();
        } else {
            return false;
        }
        self::$last_mtime || self::$last_mtime = time();
        foreach ($monitor_dirs as $monitor_dir) {
            // recursive traversal directory
            $dir_iterator = new RecursiveDirectoryIterator($monitor_dir);
            $iterator = new RecursiveIteratorIterator($dir_iterator);
            foreach ($iterator as $file) {
                // only check php files
                if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                    continue;
                }
                // check mtime
                if (self::$last_mtime < $file->getMTime()) {
                    self::$last_mtime = $file->getMTime();
                    return true;
                }
            }
        }
        return false;
    }
}