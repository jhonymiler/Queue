<?php

namespace Queue\Trait;

use Queue\Queue;

trait Dispatchable
{
    public function dispatch()
    {
        Queue::dispatch($this);
    }
}
