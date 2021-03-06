<?php


namespace tasker\queue;

use tasker\exception\RedisException;
use tasker\traits\Singleton;

/**
 * Class Redis
 * @package tasker\queue
 */
class Redis
{
    use Singleton;
    /**@var \Redis */
    protected $redis;

    /**
     * Redis constructor.
     * @param $cfg
     * @throws RedisException
     */
    private function __construct($cfg)
    {
        //连接数据库，选择数据库
        $this->redis = new \Redis();
        $this->redis->connect($cfg['host'], $cfg['port'], $cfg['timeout']);
        if (!empty($cfg['pwd'])) {
            $this->redis->auth($cfg['pwd']);
        }
        if (0 != $cfg['db']) {
            $this->redis->select($cfg['db']);
        }
        try {
            if (false === $this->redis->ping()) {
                throw new RedisException('ping redis error');
            }
        } catch (\RedisException $e) {
            throw new RedisException($e->getMessage());
        }
    }

    private function __clone()
    {
    }

    /**
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @throws RedisException
     */
    public function __call($method, $arguments)
    {
        try {
            return call_user_func([$this->redis, $method], ...$arguments);
        } catch (\RedisException $e) {
            throw new RedisException($e->getMessage());
        }
    }
}