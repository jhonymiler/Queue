<?php

namespace Queue;

use Queue\Contracts\JobInterface;

class Worker
{
    protected Queue $queue;
    protected bool $shouldStop = false;
    protected int $sleepMs;
    protected int $processed = 0;
    protected int $failed = 0;
    protected bool $useBlocking;

    public function __construct(?Queue $queue = null, ?array $config = null)
    {
        $config = $config ?? require __DIR__ . '/../config.php';
        $this->queue = $queue ?? Queue::getInstance();
        $this->sleepMs = $config['worker']['sleep_ms'];
        $this->useBlocking = true;

        $this->registerSignalHandlers();
    }

    public function listen(): void
    {
        $this->log("Worker iniciado (PID: " . getmypid() . ")", 'green');

        while (!$this->shouldStop) {
            $this->processNextJob();
        }

        $this->log("Worker encerrado graciosamente. Processados: {$this->processed}, Falhas: {$this->failed}", 'yellow');
    }

    public function processNextJob(): bool
    {
        $jobData = $this->useBlocking
            ? $this->queue->popBlocking(2)
            : $this->queue->pop();

        if (!$jobData) {
            if (!$this->useBlocking) {
                usleep($this->sleepMs * 1000);
            }
            return false;
        }

        return $this->executeJob($jobData);
    }

    protected function executeJob(array $jobData): bool
    {
        $id = $jobData['id'];
        $className = $jobData['class'];
        $instance = $jobData['instance'];

        try {
            if (!method_exists($instance, 'handle')) {
                throw new \RuntimeException("Job {$className} não possui método handle()");
            }

            $instance->handle();
            $this->processed++;
            $this->log("OK [{$id}] {$className} (tentativa: " . ($jobData['tries'] + 1) . ")", 'green');

            return true;
        } catch (\Throwable $e) {
            $this->failed++;
            $this->log("ERRO [{$id}] {$className}: {$e->getMessage()}", 'red');
            $this->queue->retry($jobData);

            return false;
        }
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function getFailed(): int
    {
        return $this->failed;
    }

    public function setUseBlocking(bool $useBlocking): void
    {
        $this->useBlocking = $useBlocking;
    }

    protected function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
        pcntl_signal(SIGQUIT, fn () => $this->stop());
    }

    protected function log(string $message, string $color = 'green'): void
    {
        $colors = [
            'green'  => "\033[0;32m",
            'red'    => "\033[0;31m",
            'yellow' => "\033[0;33m",
            'blue'   => "\033[0;34m",
            'reset'  => "\033[0m",
        ];

        $colorCode = $colors[$color] ?? $colors['green'];
        $timestamp = date('Y-m-d H:i:s');
        echo "{$colorCode}[{$timestamp}] {$message}{$colors['reset']}\n";
    }
}
