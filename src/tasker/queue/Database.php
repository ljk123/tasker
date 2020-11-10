<?php


namespace tasker\queue;


use PDO;
use PDOException;
use tasker\exception\DatabaseException;
use tasker\traits\Singleton;

/**
 * Class Database
 * @package tasker\queue
 */
class Database
{
    use Singleton;
    /**@var PDO */
    protected $pdo;

    /**
     * Database constructor.
     * @param $cfg
     * @throws DatabaseException
     */
    private function __construct($cfg)
    {
        try {
            //连接数据库，选择数据库
            $pdo = new PDO("mysql:host=" . $cfg['host'] . ":" . $cfg['port'] . ";dbname=" . $cfg['db'] . ";charset=" . $cfg['charset'], $cfg['user'], $cfg['pwd']);
        } catch (PDOException $e) {
            //输出异常信息
            throw new DatabaseException('fail to connect db:' . $e->getMessage());
        }

        $this->pdo = $pdo;
    }

    private function __clone()
    {
    }

    /**
     * @param $sql
     * @return array
     * @throws DatabaseException
     */
    public function query($sql)
    {
        $PDOStatement = $this->pdo->prepare($sql);
        if (false === $PDOStatement) {
            throw new DatabaseException('sql error', $sql);
        }
        try {
            $result = $PDOStatement->execute();
            if (false === $result) {
                throw new DatabaseException('sql execute error errorCode:' . $PDOStatement->errorCode() . ' errMsg' . join('_', $PDOStatement->errorInfo()) . ' sql:' . $sql, $sql);
            } else {
                return $PDOStatement->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\PDOException $e) {
            $message = "pdo exception";
            if ($PDOStatement) {
                $error = $PDOStatement->errorInfo();
                $message = $error[1] . ':' . $error[2];
            }
            throw new DatabaseException($message, $sql);
        }
    }

    /**
     * @param string $sql
     * @return int
     * @throws DatabaseException
     */
    public function exce($sql)
    {
        $PDOStatement = $this->pdo->prepare($sql);
        if (false === $PDOStatement) {
            throw new DatabaseException('sql error', $sql);
        }
        try {
            $result = $PDOStatement->execute();
            if (false === $result) {
                throw new DatabaseException('sql execute error', $sql);
            } else {
                return $PDOStatement->rowCount();
            }
        } catch (PDOException $e) {
            $message = "pdo exception";
            if ($PDOStatement) {
                $error = $PDOStatement->errorInfo();
                $message = $error[1] . ':' . $error[2];
            }
            throw new DatabaseException($message, $sql);
        }
    }

    /**
     * @return bool
     */
    public function ping()
    {
        try {
            $this->pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'MySQL server has gone away') !== false) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $method
     * @param $arguments
     * @return mixed
     * @throws DatabaseException
     */
    public function __call($method, $arguments)
    {
        try {
            return call_user_func([$this->pdo, $method], ...$arguments);
        } catch (PDOException $e) {
            throw new DatabaseException($e->getMessage());
        }
    }
}