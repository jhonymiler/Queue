<?php

require 'vendor/autoload.php';

use Queue\Job\ExampleJob;
use Queue\Job\MeuJob;
use Queue\Queue;
use Queue\Services\Cachorro;

$argv = $_SERVER['argv'];

for ($i = 0; $i < 1000; $i++) {
    $job = new MeuJob(new Cachorro(), "{$argv[2]} - {$i}");
    $job->dispatch();

    Queue::dispatch(new ExampleJob(new Cachorro(), "{$argv[1]} - {$i}"));
}
// mata todos processos
// pkill -f 'php worker.php' && pkill -f 'php run.php' && rm output.log

// conta quantos processos estão rodando
// pgrep -c -f 'php worker.php'

// roda em background
// php worker.php >> output.log 2>&1 &

// acompanha o log
// tail -f output.log

// lista todos processos
//ps aux | grep 'php worker.php'
