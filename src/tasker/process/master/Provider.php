<?php


namespace tasker\process\master;


use PDO;
use tasker\exception\DatabaseException;
use tasker\Op;
use tasker\queue\Database;
use tasker\queue\Redis;

class Provider
{
    /**
     *
     * @param $cfg
     * @throws DatabaseException
     */
    public static function moveToList($cfg){
        //从database移到redis
        /**@var $redis \Redis | Redis */
        $redis=Redis::getInstance($cfg['redis']);
        if($redis->lLen($cfg['redis']['queue_key'])>$cfg['worker_nums']*10)
        {
            return;
        }
        /**@var $db PDO | Database*/
        $db=Database::getInstance($cfg['database']);
        $result=$db->query('select id,payload,dotimes from ' . $cfg['database']['table'] .
            ' where doat<' . time() . ' and dotimes<' . $cfg['retry_count'] .
            ' and startat=0 limit '.($cfg['worker_nums']*2));
        if($result)
        {
            $ids=array_column($result,'id');
            $db->beginTransaction();
            $nums=$db->exce('update ' .
                $cfg['database']['table'] . ' set startat=' . time() .
                ',dotimes=dotimes+1 where startat=0 and id in (' .join(',',$ids). ')');
            if($nums!==count($ids))
            {
                //不一样 还原
                $db->rollBack();
                return;
            }
            $db->commit();
            foreach ($result as $task)
            {
                $tasker=[
                    'payload'=>$task['payload'],
                    'id'=>$task['id'],
                ];
                $redis->lpush($cfg['redis']['queue_key'],serialize($tasker));
            }
        }
        else{
            //如果队列长度为空 吧为完成的改回去
            if($redis->lLen($cfg['redis']['queue_key'])==0)
            {
                $sql='SELECT count(1) counts FROM '.$cfg['database']['table'].
                    ' WHERE dotimes<10 and  startat>0 and endat=0 and startat<'.(time()-600);
                $res=$db->query($sql);
                if($res[0]['counts']>0)
                {
                    //10分钟前开始 没完成的
                    $db->exce('UPDATE ' .
                        $cfg['database']['table'] . ' SET startat=0 where dotimes<10 and  startat>0 and endat=0 and startat<'.(time()-600));
                }
            }
        }
    }

}