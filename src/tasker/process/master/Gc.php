<?php


namespace tasker\process\master;


use tasker\exception\DatabaseException;
use tasker\queue\Database;

class Gc
{
    /**
     * 删除表里执行成功N天前的删除数据 防止表过大
     * @param $cfg
     * @throws DatabaseException
     */
    public static function table($cfg)
    {
        static $last_gc_time = 0;
        if (is_null($cfg['gc_table_day']) || intval($cfg['gc_table_day']) < 0 || time() - $last_gc_time < 86400) {
            return;
        }
        $delete_start_time = strtotime('today -' . $cfg['gc_table_day'] . ' days');
        /**@var $db Database */
        $db = Database::getInstance($cfg['database']);
        $db->exce("DELETE FROM " . $cfg['database']['table'] . " where startat>0 and endat>0 and dotimes<10 and startat<" . $delete_start_time);
        $last_gc_time = time();

    }

}