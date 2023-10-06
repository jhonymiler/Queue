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

php queue work:start  //Inicia o trabalho
php queue work:stop   //Para o trabalho
php queue work:list   //Lista os workers ativos
php queue logs        //Acompanha o log em tempo real
```


