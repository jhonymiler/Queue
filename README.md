# Instalação

## Composer
    
```bash
composer install
```
## Suba um container Redis

```bash
docker run --name redis -p 6379:6379 -d redis
```

## Comandos para rodar

```bash
php index.php "au au au" "miau miau miau"
php worker.php
```


