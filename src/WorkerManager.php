<?php

namespace Queue;

class WorkerManager
{
    protected Queue $queue;
    protected int $maxWorkers;
    protected int $minWorkers;
    protected int $jobsPerWorker;
    protected int $sleepMs;
    protected bool $shouldStop = false;

    public function __construct(?Queue $queue = null, ?array $config = null)
    {
        $config = $config ?? require __DIR__ . '/../config.php';
        $this->queue = $queue ?? Queue::getInstance();
        $this->maxWorkers = $config['worker']['max_workers'];
        $this->minWorkers = $config['worker']['min_workers'];
        $this->jobsPerWorker = $config['worker']['jobs_per_worker'];
        $this->sleepMs = $config['worker']['sleep_ms'];

        $this->registerSignalHandlers();
    }

    public function manageWorkers(): void
    {
        while (!$this->shouldStop) {
            $totalJobs = $this->queue->count();
            $desiredWorkers = $this->calculateDesiredWorkers($totalJobs);
            $runningWorkers = $this->getRunningWorkers();
            $currentCount = count($runningWorkers);

            if ($desiredWorkers > $currentCount && $currentCount < $this->maxWorkers) {
                $toStart = min($desiredWorkers - $currentCount, $this->maxWorkers - $currentCount);
                for ($i = 0; $i < $toStart; $i++) {
                    $this->startNewWorker();
                }
            } elseif ($desiredWorkers < $currentCount) {
                $toStop = $currentCount - $desiredWorkers;
                $this->stopWorkers($runningWorkers, $toStop);
            }

            usleep($this->sleepMs * 1000);
        }
    }

    public function calculateDesiredWorkers(int $totalJobs): int
    {
        $desired = (int) ceil($totalJobs / $this->jobsPerWorker);

        return max($this->minWorkers, min($desired, $this->maxWorkers));
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }

    protected function startNewWorker(): void
    {
        $workerScript = __DIR__ . '/../worker.php';
        $logFile = __DIR__ . '/../output.log';
        exec("php {$workerScript} >> {$logFile} 2>&1 &");
    }

    protected function stopWorkers(array $workers, int $count): void
    {
        $stopped = 0;
        foreach ($workers as $worker) {
            if ($stopped >= $count) {
                break;
            }
            exec("kill -TERM {$worker['pid']}");
            $stopped++;
        }
    }

    public function getRunningWorkers(): array
    {
        $output = [];
        exec("ps aux | grep 'php.*worker.php' | grep -v grep", $output);
        $workers = [];

        foreach ($output as $line) {
            $data = preg_split('/\s+/', $line);
            if (isset($data[1])) {
                $workers[] = ['pid' => (int) $data[1]];
            }
        }

        return $workers;
    }

    protected function registerSignalHandlers(): void
    {
        if (!function_exists('pcntl_async_signals')) {
            return;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn () => $this->stop());
        pcntl_signal(SIGINT, fn () => $this->stop());
    }
}
