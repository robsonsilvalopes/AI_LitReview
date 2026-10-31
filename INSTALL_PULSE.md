# Instalação do Laravel Pulse

O pacote `laravel/pulse` já foi adicionado ao `composer.json`. Siga os passos abaixo para completar a instalação:

## Passo 1: Instalar o pacote

Execute o comando abaixo quando tiver conectividade de rede:

```bash
docker compose exec app composer install
# ou
docker compose exec app composer update laravel/pulse
```

## Passo 2: Publicar migrações e configurações

```bash
# Publicar migrações
docker compose exec app php artisan vendor:publish --tag=pulse-migrations

# Publicar configuração (opcional)
docker compose exec app php artisan vendor:publish --tag=pulse-config
```

## Passo 3: Executar migrações

```bash
docker compose exec app php artisan migrate
```

## Passo 4: Configurar o agendamento

O agendamento do Pulse já foi configurado no `bootstrap/app.php`. O comando `pulse:check` será executado automaticamente a cada minuto quando o agendador do Laravel estiver rodando.

**Importante:** Certifique-se de que o agendador do Laravel está rodando. Adicione ao crontab do servidor:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Ou, se estiver usando Docker, configure um container separado para o agendador.

## Passo 5: Acessar o dashboard

Após a instalação, o dashboard do Pulse estará disponível em:
- URL: `http://localhost:8000/pulse`
- Por padrão, requer autenticação (middleware `auth`)

## Configuração adicional

O Pulse pode ser configurado através do arquivo `config/pulse.php` (publicado no Passo 2).

Para mais informações, consulte a [documentação oficial do Laravel Pulse](https://laravel.com/docs/pulse).
