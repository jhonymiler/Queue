<?php

require __DIR__ . '/vendor/autoload.php';

use Queue\WorkerManager;

$manager = new WorkerManager();
$manager->manageWorkers();
