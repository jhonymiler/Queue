<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Queue\Queue;
use Queue\WorkerManager;
use Queue\Job\ExampleJob;
use Queue\Services\Cachorro;

class WorkerManagerTest extends TestCase
{
    private Queue $queue;
    private WorkerManager $manager;
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
                'name'        => 'test_manager_queue_' . getmypid(),
                'failed_name' => 'test_manager_failed_' . getmypid(),
                'max_retries' => 3,
            ],
            'worker' => [
                'min_workers'     => 1,
                'max_workers'     => 10,
                'jobs_per_worker' => 30,
                'sleep_ms'        => 100,
            ],
        ];

        Queue::resetInstance();
        $this->queue = new Queue($this->config);
        $this->manager = new WorkerManager($this->queue, $this->config);
    }

    protected function tearDown(): void
    {
        $this->queue->flush();
        $this->queue->flushFailed();
        Queue::resetInstance();
    }

    public function testCalculateDesiredWorkersReturnsMinimum(): void
    {
        $result = $this->manager->calculateDesiredWorkers(0);
        $this->assertEquals(1, $result);
    }

    public function testCalculateDesiredWorkersScalesUp(): void
    {
        $result = $this->manager->calculateDesiredWorkers(90);
        $this->assertEquals(3, $result);
    }

    public function testCalculateDesiredWorkersRespectsMaximum(): void
    {
        $result = $this->manager->calculateDesiredWorkers(10000);
        $this->assertEquals(10, $result);
    }

    public function testCalculateDesiredWorkersRoundsUp(): void
    {
        $result = $this->manager->calculateDesiredWorkers(31);
        $this->assertEquals(2, $result);
    }

    public function testCalculateDesiredWorkersEdgeCases(): void
    {
        $this->assertEquals(1, $this->manager->calculateDesiredWorkers(1));
        $this->assertEquals(1, $this->manager->calculateDesiredWorkers(30));
        $this->assertEquals(2, $this->manager->calculateDesiredWorkers(31));
        $this->assertEquals(2, $this->manager->calculateDesiredWorkers(60));
        $this->assertEquals(3, $this->manager->calculateDesiredWorkers(61));
    }

    public function testGetRunningWorkersReturnsArray(): void
    {
        $workers = $this->manager->getRunningWorkers();
        $this->assertIsArray($workers);
    }

    public function testStopFlagPreventsLoop(): void
    {
        $this->manager->stop();

        $start = microtime(true);
        $this->manager->manageWorkers();
        $elapsed = microtime(true) - $start;

        $this->assertLessThan(1.0, $elapsed);
    }
}
