<?php

namespace Queue;

class Worker
{
    protected $queue;
    protected $maxRetries = 3; // Número máximo de retentativas permitidas

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
            }
        }
    }

    public function listen()
    {
        $this->processNextJob();
    }

    private function printColoredString($string, $color)
    {
        $coloredString = "\033[0;32m$string\033[0m";
        echo "$coloredString\n";
    }
}
