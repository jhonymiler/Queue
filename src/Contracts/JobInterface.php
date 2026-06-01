<?php

namespace Queue\Contracts;

interface JobInterface
{
    public function handle(): void;

    public function getId(): string;
}
