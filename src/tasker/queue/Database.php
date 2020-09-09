<?php


namespace tasker\queue;


use PDO;
use PDOException;
use tasker\exception\DatabaseException;
use tasker\traits\Singleton;

class Database
{
    use Singleton;
    /**@var PDO */
    protected $pdo;
    private function __construct($cfg)
    {
        try{
            //连接数据库，选择数据库
            $pdo = new PDO("mysql:host=".$cfg['host'].":".$cfg['port'].";dbname=".$cfg['db'].";charset=".$cfg['charset'],$cfg['user'],$cfg['pwd']);
        } catch (PDOException $e){
            //输出异常信息
            throw new DatabaseException('fail to connect db:'.$e->getMessage());
        }

        $this->pdo=$pdo;
    }
    private function __clone()
    {
    }

    public function query($sql){
        $PDOStatement =   $this->pdo->prepare($sql);
        if(false === $PDOStatement) {
            throw new DatabaseException('sql error' ,$sql);
        }
        try{
            $result =   $PDOStatement->execute();
            if ( false === $result ) {
                throw new DatabaseException('sql execute error' ,$sql);
            } else {
                return $PDOStatement->fetchAll(PDO::FETCH_ASSOC);
            }
        }catch (\PDOException $e) {
            $message="pdo exception";
            if($PDOStatement)
            {
                $error = $PDOStatement->errorInfo();
                $message=$error[1].':'.$error[2];
            }
            throw new DatabaseException($message ,$sql);
        }
    }
    public function exce($sql){
        $PDOStatement =   $this->pdo->prepare($sql);
        if(false === $PDOStatement) {
            throw new DatabaseException('sql error' ,$sql);
        }
        try{
            $result =   $PDOStatement->execute();
            if ( false === $result) {
                throw new DatabaseException('sql execute error' ,$sql);
            } else {
                return $PDOStatement->rowCount();
            }
        }catch (\PDOException $e) {
            $message="pdo exception";
            if($PDOStatement)
            {
                $error = $PDOStatement->errorInfo();
                $message=$error[1].':'.$error[2];
            }
            throw new DatabaseException($message ,$sql);
        }
    }
    public function ping(){
        try{
            $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (PDOException $e) {
            if(strpos($e->getMessage(), 'MySQL server has gone away')!==false){
                return false;
            }
        }
        return true;
    }
    public function __call($method, $arguments)
    {
        return call_user_func([$this->pdo,$method],...$arguments);
    }
}