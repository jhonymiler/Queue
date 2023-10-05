<?php

namespace Queue\Job;

use Queue\Services\Cachorro;
use Queue\Trait\Dispatchable;

class MeuJob
{
    use Dispatchable;

    public function __construct(private Cachorro $cachorro, private $frase)
    {
    }

    public function handle()
    {
        $this->cachorro->setFrase($this->frase);
        $this->cachorro->latir();
    }
}
