<?php

require __DIR__.'/vendor/autoload.php';

use Queue\WorkerManager;

$workerManager = new WorkerManager();
$workerManager->manageWorkers();
