<?php

namespace Queue\Trait;

use Queue\Queue;

trait Dispatchable
{
    public function dispatch(): string
    {
        return Queue::dispatch($this);
    }
}
