<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Queue\Queue;
use Queue\Job\ExampleJob;
use Queue\Services\Cachorro;

class QueueTest extends TestCase
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
                'name'        => 'test_queue_' . getmypid(),
                'failed_name' => 'test_failed_' . getmypid(),
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
    }

    protected function tearDown(): void
    {
        $this->queue->flush();
        $this->queue->flushFailed();
        Queue::resetInstance();
    }

    public function testPushAddsJobToQueue(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'teste');
        $this->queue->push($job);

        $this->assertEquals(1, $this->queue->count());
    }

    public function testPushReturnsJobId(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'teste');
        $id = $this->queue->push($job);

        $this->assertNotEmpty($id);
        $this->assertEquals(32, strlen($id));
    }

    public function testPopReturnsJobData(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'teste pop');
        $this->queue->push($job);

        $result = $this->queue->pop();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('class', $result);
        $this->assertArrayHasKey('instance', $result);
        $this->assertArrayHasKey('tries', $result);
        $this->assertEquals(ExampleJob::class, $result['class']);
        $this->assertEquals(0, $result['tries']);
    }

    public function testPopReturnsNullWhenEmpty(): void
    {
        $result = $this->queue->pop();
        $this->assertNull($result);
    }

    public function testPopDecreasesCount(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'teste count');
        $this->queue->push($job);
        $this->queue->push($job);

        $this->assertEquals(2, $this->queue->count());

        $this->queue->pop();
        $this->assertEquals(1, $this->queue->count());
    }

    public function testRetryReenqueuesJobWithIncrementedTries(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'teste retry');
        $this->queue->push($job);
        $jobData = $this->queue->pop();

        $this->queue->retry($jobData);

        $retried = $this->queue->pop();
        $this->assertNotNull($retried);
        $this->assertEquals(1, $retried['tries']);
    }

    public function testRetryMovesToFailedAfterMaxRetries(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'teste failed');
        $this->queue->push($job);
        $jobData = $this->queue->pop();

        $jobData['tries'] = 2; // Already tried twice, next retry = 3rd = max
        $this->queue->retry($jobData);

        $this->assertEquals(0, $this->queue->count());
        $this->assertEquals(1, $this->queue->failedCount());
    }

    public function testMultipleRetriesPreserveTriesCount(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'multi retry');
        $this->queue->push($job);
        $jobData = $this->queue->pop();

        $this->queue->retry($jobData);
        $jobData = $this->queue->pop();
        $this->assertEquals(1, $jobData['tries']);

        $this->queue->retry($jobData);
        $jobData = $this->queue->pop();
        $this->assertEquals(2, $jobData['tries']);

        $this->queue->retry($jobData);
        $this->assertEquals(0, $this->queue->count());
        $this->assertEquals(1, $this->queue->failedCount());
    }

    public function testFlushClearsQueue(): void
    {
        $job = new ExampleJob(new Cachorro(0), 'flush');
        $this->queue->push($job);
        $this->queue->push($job);

        $this->queue->flush();
        $this->assertEquals(0, $this->queue->count());
    }

    public function testFifoOrder(): void
    {
        $job1 = new ExampleJob(new Cachorro(0), 'primeiro');
        $job2 = new ExampleJob(new Cachorro(0), 'segundo');

        $id1 = $this->queue->push($job1);
        $id2 = $this->queue->push($job2);

        $result1 = $this->queue->pop();
        $result2 = $this->queue->pop();

        $this->assertEquals($id1, $result1['id']);
        $this->assertEquals($id2, $result2['id']);
    }

    public function testGetMaxRetries(): void
    {
        $this->assertEquals(3, $this->queue->getMaxRetries());
    }

    public function testGetQueueName(): void
    {
        $this->assertStringStartsWith('test_queue_', $this->queue->getQueueName());
    }
}
