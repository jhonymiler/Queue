<?php

namespace Queue\Job;

use Queue\Services\Cachorro;

class ExampleJob
{
    public function __construct(private Cachorro $cachorro, private $frase)
    {
    }

    public function handle()
    {
        $this->cachorro->setFrase($this->frase);
        $this->cachorro->latir();
    }
}