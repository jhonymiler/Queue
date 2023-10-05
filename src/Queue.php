<?php

namespace Queue;

use Predis\Client;

class Queue
{
    private static $instance;
    protected $redis;

    public function __construct()
    {
        $this->redis = new Client([
            'scheme'             => 'tcp',
            'host'               => '127.0.0.1',
            'port'               => 6379,
            'password'           => null,
            'database'           => 0,
            'timeout'            => 0.1,
            'read_write_timeout' => 10,
        ]);
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function push($job): void
    {
        $jobData = [
            'class' => get_class($job),
            'data'  => serialize($job),
            'tries' => 0,
        ];
        $this->redis->rpush('queue', json_encode($jobData));
    }

    public function pop(): ?array
    {
        $jobData = $this->redis->lpop('queue');
        if ($jobData) {
            $jobData = json_decode($jobData, true);
            $jobInstance = unserialize($jobData['data']);

            // Obtém a chave do registro no Redis
            $redisIndex = $this->redis->llen('queue') - 1;

            return ['index' => $redisIndex, 'class' => $jobData['class'], 'instance' => $jobInstance];
        }

        return null;
    }

    public function count(): int
    {
        return $this->redis->llen('queue');
    }

    public function releaseFailedJob(array $jobData): void
    {
        $jobData['tries']++;
        if ($jobData['tries'] < 3) {
            $this->redis->rpush('queue', json_encode($jobData));
        } else {
            $this->redis->rpush('failed_queue', json_encode($jobData));
        }
    }

    public static function dispatch($job): void
    {
        $queue = self::getInstance();
        $queue->push($job);
    }
}
