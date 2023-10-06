<?php

namespace Queue;

class WorkerManager
{
    protected $queue;
    protected $maxWorkers = 30; // Número máximo de workers permitidos
    protected $jobsPerWorker = 100; // Número de trabalhos por worker
    protected $totalJobs = 0; // Inicialize a propriedade totalJobs

    protected $minWorkers = 3; // Número mínimo de workers permitidos

    public function __construct()
    {
        $this->queue = Queue::getInstance();
    }

    public function manageWorkers(): void
    {
        while (true) {
            $totalJobs = $this->queue->count(); // Obtém o número total de trabalhos na fila
            $totalWorkers = max($this->minWorkers, ceil($totalJobs / $this->jobsPerWorker)); // Garante no mínimo 3 workers

            // Cria novos workers se necessário
            while ($totalWorkers > count($this->getRunningWorkers()) && count($this->getRunningWorkers()) < $this->maxWorkers) {
                $this->startNewWorker();
            }

            // Remove workers extras se necessário
            while ($totalWorkers < count($this->getRunningWorkers())) {
                $this->stopWorker();
            }

            // Dorme por um curto período antes de verificar novamente
            sleep(0.1);
        }
    }

    protected function startNewWorker()
    {
        $workerCommand = 'php worker.php >> output.log 2>&1 &';
        exec($workerCommand); // Inicia um novo worker em background
    }

    protected function stopWorker()
    {
        $runningWorkers = $this->getRunningWorkers();
        if (!empty($runningWorkers)) {
            $workerToStop = reset($runningWorkers);
            $stopCommand = 'kill '.$workerToStop['pid'];
            exec($stopCommand); // Mata o worker especificado
        }
    }

    protected function getRunningWorkers()
    {
        $output = [];
        exec("ps aux | grep 'php worker.php' | grep -v grep", $output); // Obtém a lista de workers em execução
        $workers = [];

        foreach ($output as $line) {
            $data = preg_split('/\s+/', $line);
            $pid = $data[1];
            $workers[] = ['pid' => $pid];
        }

        return $workers;
    }
}
