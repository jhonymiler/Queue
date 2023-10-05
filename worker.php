<?php

require __DIR__.'/vendor/autoload.php';

use Queue\Worker;

$worker = new Worker();
$worker->listen();
