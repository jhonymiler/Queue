<?php

namespace Queue;

class Worker
{
    protected $queue;
    protected $maxRetries = 3; // Número máximo de retentativas permitidas

    protected $colors = [
        'black'        => '0;30',
        'dark_gray'    => '1;30',
        'blue'         => '0;34',
        'light_blue'   => '1;34',
        'green'        => '0;32',
        'light_green'  => '1;32',
        'cyan'         => '0;36',
        'light_cyan'   => '1;36',
        'red'          => '0;31',
        'light_red'    => '1;31',
        'purple'       => '0;35',
        'light_purple' => '1;35',
        'brown'        => '0;33',
        'yellow'       => '1;33',
        'light_gray'   => '0;37',
        'white'        => '1;37',
    ];

    public function __construct()
    {
        $this->queue = Queue::getInstance();
    }

    public function processNextJob()
    {
        $totalJobs = $this->queue->count(); // Obtém o número total de trabalhos na fila
        $processedJobs = 0; // Inicializa o contador de trabalhos processados

        while (true) {
            $jobData = $this->queue->pop();

            if ($totalJobs == 0) {
                $totalJobs = $this->queue->count();
            }

            if ($jobData) {
                try {
                    $className = $jobData['class'];
                    $instance = $jobData['instance'];
                    $index = $jobData['index'];

                    // Verifica se a classe existe
                    if (class_exists($className)) {
                        $instance->handle(); // Chama o método handle() na instância do trabalho

                        // Imprime informações coloridas sobre o trabalho executado
                        $this->printColoredString("Executado: [$index] - $className", 'green');
                    }
                    $processedJobs++;
                    $this->printLoadingBar($processedJobs, $totalJobs);
                } catch (\Exception $e) {
                    $errorMessage = 'Erro ao processar trabalho: '.$e->getMessage();
                    $this->printColoredString($errorMessage, 'red');

                    // Incrementa o número de tentativas
                    $jobData['tries']++;

                    // Se o número de tentativas for menor que o máximo permitido, reenvia o trabalho
                    if ($jobData['tries'] < $this->maxRetries) {
                        $this->queue->push($instance);
                    } else {
                        // Se excedeu o número máximo de tentativas, libera o trabalho
                        $this->queue->releaseFailedJob($jobData);
                    }
                }
            } else {
                // O worker dorme por 0.1 segundo e continua a verificar a fila.
                sleep(0.1); // 0.1 segundo em microssegundos
                $this->printLoadingBar($processedJobs, $totalJobs);
            }
        }
    }

    public function listen()
    {
        $this->processNextJob();
    }

    private function printColoredString($string, $color)
    {
        $coloredString = "\033[".$this->colors[$color]."m$string\033[0m";
        echo "$coloredString\n";
    }

    private function printLoadingBar($processedJobs, $totalJobs)
    {
        $loadingChars = ['|', '/', '-', '\\'];
        static $idx = 0;
        $percentage = ($totalJobs > 0) ? round(($processedJobs / $totalJobs) * 100, 2) : 0;
        $loadingInfo = "Processando: $processedJobs de $totalJobs ($percentage%) ".$loadingChars[$idx % 4]."\r";

        echo "\033[K"; // Limpa a linha do console
        echo "\033[".$this->colors['purple']."m$loadingInfo\033[0m";
        $idx++;
    }
}
