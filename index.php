<?php

require 'vendor/autoload.php';

use Queue\Job\ExampleJob;
use Queue\Job\MeuJob;
use Queue\Queue;
use Queue\Services\Cachorro;

$argv = $_SERVER['argv'];

for ($i = 0; $i < 15; $i++) {
    $job = new MeuJob(new Cachorro(), "{$argv[2]} - {$i}");
    $job->dispatch();

    Queue::dispatch(new ExampleJob(new Cachorro(), "{$argv[1]} - {$i}"));
}
