<?php

namespace Queue\Services;

class Cachorro
{
    protected string $frase = '';
    protected int $sleepSeconds;

    public function __construct(int $sleepSeconds = 1)
    {
        $this->sleepSeconds = $sleepSeconds;
    }

    public function setFrase(string $frase): self
    {
        $this->frase = $frase;

        return $this;
    }

    public function latir(): bool
    {
        sleep($this->sleepSeconds);

        return true;
    }

    public function getFrase(): string
    {
        return $this->frase;
    }
}
