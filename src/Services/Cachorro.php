<?php

namespace Queue\Services;

class Cachorro
{
    protected $frase;

    public function setFrase($frase)
    {
        $this->frase = $frase;
    }
    public function latir()
    {
        //echo "{$this->frase}! \n";
        sleep(1);

        return true;
    }
}
