<?php

namespace Queue\Job;

use Queue\Contracts\JobInterface;
use Queue\Services\Cachorro;
use Queue\Trait\Dispatchable;

class MeuJob implements JobInterface
{
    use Dispatchable;

    private string $id;

    public function __construct(private Cachorro $cachorro, private string $frase)
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function handle(): void
    {
        $this->cachorro->setFrase($this->frase);
        $this->cachorro->latir();
    }

    public function getId(): string
    {
        return $this->id;
    }
}
