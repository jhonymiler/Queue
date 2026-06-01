<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Queue\Queue;
use Queue\Worker;
use Queue\Job\ExampleJob;
use Queue\Services\Cachorro;

class WorkerTest extends TestCase
{
    private Queue $queue;
    private array $config;

    protected function setUp(): void
    {
        $this->config = [
            'redis' => [
                'scheme'             => 'tcp',
                'host'               => getenv('REDIS_HOST') ?: '127.0.0.1',
                'port'               => (int) (getenv('REDIS_PORT') ?: 6379),
                'password'           => null,
                'database'           => 15,
                'timeout'            => 5.0,
                'read_write_timeout' => 30,
            ],
            'queue' => [
                'name'        => 'test_worker_queue_' . getmypid(),
                'failed_name' => 'test_worker_failed_' . getmypid(),
                'max_retries' => 3,
            ],
            'worker' => [
                'min_workers'     => 1,
                'max_workers'     => 10,
                'jobs_per_worker' => 30,
                'sleep_ms'        => 10,
            ],
        ];

        Queue::resetInstance();
        $this->queue = new Queue($this->config);
    }

    protected function tearDown(): void
    {
        $this->queue->flush();
        $this->queue->flushFailed();
        Queue::resetInstance();
    }

    public function testProcessNextJobExecutesHandle(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'worker test');
        $this->queue->push($job);

        $worker = new Worker($this->queue, $this->config);
        $worker->setUseBlocking(false);

        $result = $worker->processNextJob();

        $this->assertTrue($result);
        $this->assertEquals(1, $worker->getProcessed());
        $this->assertEquals(0, $worker->getFailed());
    }

    public function testProcessNextJobReturnsFalseWhenEmpty(): void
    {
        $worker = new Worker($this->queue, $this->config);
        $worker->setUseBlocking(false);

        $result = $worker->processNextJob();

        $this->assertFalse($result);
        $this->assertEquals(0, $worker->getProcessed());
    }

    public function testWorkerRetriesFailedJob(): void
    {
        $failingJob = new FailingJob();
        $this->queue->push($failingJob);

        $worker = new Worker($this->queue, $this->config);
        $worker->setUseBlocking(false);

        $worker->processNextJob();

        $this->assertEquals(0, $worker->getProcessed());
        $this->assertEquals(1, $worker->getFailed());
        $this->assertEquals(1, $this->queue->count());
    }

    public function testWorkerMovesToFailedAfterMaxRetries(): void
    {
        $failingJob = new FailingJob();
        $this->queue->push($failingJob);

        $worker = new Worker($this->queue, $this->config);
        $worker->setUseBlocking(false);

        // Process 3 times (max retries)
        $worker->processNextJob(); // tries: 0 -> 1
        $worker->processNextJob(); // tries: 1 -> 2
        $worker->processNextJob(); // tries: 2 -> 3 (move to failed)

        $this->assertEquals(0, $this->queue->count());
        $this->assertEquals(1, $this->queue->failedCount());
        $this->assertEquals(3, $worker->getFailed());
    }

    public function testProcessMultipleJobs(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->queue->push(new ExampleJob(new Cachorro(0), "job {$i}"));
        }

        $worker = new Worker($this->queue, $this->config);
        $worker->setUseBlocking(false);

        for ($i = 0; $i < 5; $i++) {
            $worker->processNextJob();
        }

        $this->assertEquals(5, $worker->getProcessed());
        $this->assertEquals(0, $this->queue->count());
    }

    public function testStopPreventsListenFromContinuing(): void
    {
        $worker = new Worker($this->queue, $this->config);
        $worker->setUseBlocking(false);
        $worker->stop();

        ob_start();
        $worker->listen();
        ob_end_clean();

        $this->assertEquals(0, $worker->getProcessed());
    }
}

class FailingJob
{
    public function handle(): void
    {
        throw new \RuntimeException('Job falhou intencionalmente');
    }

    public function getId(): string
    {
        return 'failing-job-test';
    }
}
