<?php

namespace Queue;

use Predis\Client;

class Queue
{
    private static ?self $instance = null;
    protected Client $redis;
    private string $queueName;
    private string $failedQueueName;
    private int $maxRetries;

    public function __construct(?array $config = null)
    {
        $config = $config ?? require __DIR__ . '/../config.php';

        $this->redis = new Client($config['redis']);
        $this->queueName = $config['queue']['name'];
        $this->failedQueueName = $config['queue']['failed_name'];
        $this->maxRetries = $config['queue']['max_retries'];
    }

    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    public function push(object $job): string
    {
        $id = $this->generateId();
        $jobData = [
            'id'    => $id,
            'class' => get_class($job),
            'data'  => serialize($job),
            'tries' => 0,
        ];
        $this->redis->rpush($this->queueName, json_encode($jobData));

        return $id;
    }

    public function pop(): ?array
    {
        $raw = $this->redis->lpop($this->queueName);
        if (!$raw) {
            return null;
        }

        $jobData = json_decode($raw, true);
        $jobInstance = unserialize($jobData['data'], [
            'allowed_classes' => true,
        ]);

        return [
            'id'       => $jobData['id'] ?? $this->generateId(),
            'class'    => $jobData['class'],
            'instance' => $jobInstance,
            'tries'    => $jobData['tries'] ?? 0,
        ];
    }

    public function popBlocking(int $timeoutSeconds = 5): ?array
    {
        $result = $this->redis->blpop([$this->queueName], $timeoutSeconds);
        if (!$result) {
            return null;
        }

        $raw = $result[1];
        $jobData = json_decode($raw, true);
        $jobInstance = unserialize($jobData['data'], [
            'allowed_classes' => true,
        ]);

        return [
            'id'       => $jobData['id'] ?? $this->generateId(),
            'class'    => $jobData['class'],
            'instance' => $jobInstance,
            'tries'    => $jobData['tries'] ?? 0,
        ];
    }

    public function retry(array $jobData): void
    {
        $jobData['tries']++;

        if ($jobData['tries'] < $this->maxRetries) {
            $payload = [
                'id'    => $jobData['id'],
                'class' => $jobData['class'],
                'data'  => serialize($jobData['instance']),
                'tries' => $jobData['tries'],
            ];
            $this->redis->rpush($this->queueName, json_encode($payload));
        } else {
            $this->moveFailed($jobData);
        }
    }

    public function moveFailed(array $jobData): void
    {
        $payload = [
            'id'        => $jobData['id'],
            'class'     => $jobData['class'],
            'data'      => serialize($jobData['instance']),
            'tries'     => $jobData['tries'],
            'failed_at' => date('Y-m-d H:i:s'),
        ];
        $this->redis->rpush($this->failedQueueName, json_encode($payload));
    }

    public function count(): int
    {
        return $this->redis->llen($this->queueName);
    }

    public function failedCount(): int
    {
        return $this->redis->llen($this->failedQueueName);
    }

    public function flush(): void
    {
        $this->redis->del([$this->queueName]);
    }

    public function flushFailed(): void
    {
        $this->redis->del([$this->failedQueueName]);
    }

    public static function dispatch(object $job): string
    {
        return self::getInstance()->push($job);
    }

    public function getRedis(): Client
    {
        return $this->redis;
    }

    public function getQueueName(): string
    {
        return $this->queueName;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    private function generateId(): string
    {
        return bin2hex(random_bytes(16));
    }
}
