# Queue Standalone

Fila de jobs PHP + Redis inspirada no Laravel Illuminate Queue, com workers dinâmicos, retry automático e graceful shutdown.

## Requisitos

- PHP 8.1+
- Redis
- Extensão `pcntl` (para graceful shutdown)

## Instalação

```bash
composer install
```

## Configuração

Copie o arquivo de exemplo e ajuste conforme necessário:

```bash
cp .env.example .env
```

As variáveis disponíveis são:

| Variável | Padrão | Descrição |
|----------|--------|-----------|
| `REDIS_HOST` | 127.0.0.1 | Host do Redis |
| `REDIS_PORT` | 6379 | Porta do Redis |
| `REDIS_PASSWORD` | (vazio) | Senha do Redis |
| `REDIS_DATABASE` | 0 | Database do Redis |
| `QUEUE_NAME` | queue | Nome da fila principal |
| `QUEUE_FAILED_NAME` | failed_queue | Nome da fila de falhas |
| `QUEUE_MAX_RETRIES` | 3 | Máximo de tentativas por job |
| `WORKER_MIN` | 1 | Mínimo de workers |
| `WORKER_MAX` | 100 | Máximo de workers |
| `WORKER_JOBS_PER` | 30 | Jobs por worker (scaling) |
| `WORKER_SLEEP_MS` | 100 | Intervalo de polling (ms) |

## Subindo o Redis

```bash
docker run --name redis -p 6379:6379 -d redis
```

## Uso

### Enfileirando jobs

```bash
php index.php "frase1" "frase2"
```

### Gerenciando workers

```bash
php queue work:start   # Inicia o WorkerManager (auto-scaling)
php queue work:stop    # Para todos os workers
php queue work:list    # Lista workers ativos em tempo real
php queue logs         # Acompanha o log em tempo real
```

## Criando um Job

Implemente a interface `JobInterface`:

```php
<?php

namespace Queue\Job;

use Queue\Contracts\JobInterface;
use Queue\Trait\Dispatchable;

class MeuJob implements JobInterface
{
    use Dispatchable;

    private string $id;

    public function __construct(private string $dados)
    {
        $this->id = bin2hex(random_bytes(16));
    }

    public function handle(): void
    {
        // Lógica do job aqui
    }

    public function getId(): string
    {
        return $this->id;
    }
}
```

### Despachando

```php
// Via trait Dispatchable
$job = new MeuJob('dados');
$job->dispatch();

// Via método estático
Queue::dispatch(new MeuJob('dados'));
```

## Arquitetura

```
Producer (index.php)
    └── Queue::dispatch() ──→ Redis (RPUSH)

WorkerManager (run.php)
    └── Escala N workers com base no tamanho da fila

Worker (worker.php)
    └── BLPOP ──→ handle() ──→ OK ou retry
                                  └── 3 falhas → failed_queue
```

**Funcionalidades:**

- BLPOP (blocking pop) — sem busy loop
- Retry automático com contador de tentativas preservado
- Dead letter queue (`failed_queue`) após max retries
- Auto-scaling de workers baseado no tamanho da fila
- Graceful shutdown via sinais POSIX (SIGTERM, SIGINT)
- UUID único por job para rastreabilidade
- Configuração externalizada via variáveis de ambiente

## Testes

```bash
./vendor/bin/phpunit --testdox
```

Suite com 25 testes cobrindo:
- Push/Pop/FIFO
- Retry e dead letter queue
- Processamento de jobs (sucesso e falha)
- Cálculo de scaling do WorkerManager
- Graceful shutdown

## Licença

MIT
