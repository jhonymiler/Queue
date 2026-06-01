<?php

return [
    'redis' => [
        'scheme'             => getenv('REDIS_SCHEME') ?: 'tcp',
        'host'               => getenv('REDIS_HOST') ?: '127.0.0.1',
        'port'               => (int) (getenv('REDIS_PORT') ?: 6379),
        'password'           => getenv('REDIS_PASSWORD') ?: null,
        'database'           => (int) (getenv('REDIS_DATABASE') ?: 0),
        'timeout'            => 5.0,
        'read_write_timeout' => 30,
    ],

    'queue' => [
        'name'        => getenv('QUEUE_NAME') ?: 'queue',
        'failed_name' => getenv('QUEUE_FAILED_NAME') ?: 'failed_queue',
        'max_retries' => (int) (getenv('QUEUE_MAX_RETRIES') ?: 3),
    ],

    'worker' => [
        'min_workers'    => (int) (getenv('WORKER_MIN') ?: 1),
        'max_workers'    => (int) (getenv('WORKER_MAX') ?: 100),
        'jobs_per_worker' => (int) (getenv('WORKER_JOBS_PER') ?: 30),
        'sleep_ms'       => (int) (getenv('WORKER_SLEEP_MS') ?: 100),
    ],
];
