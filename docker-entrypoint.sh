#!/bin/sh
set -eu

APP_DIR=/var/www/html
cd "$APP_DIR"

log() { echo "[entrypoint] $*" >&2; }
fail() { log "ERRO: $*"; exit 1; }

# Papel do container, pelo comando recebido. Só os três processos de longa duração preparam caches;
# um comando avulso (docker compose run ... php artisan ...) roda direto com as variáveis de ambiente.
ROLE=oneoff
case "${1:-}" in
    php-fpm) ROLE=web ;;
    php)
        if [ "${2:-}" = "artisan" ]; then
            case "${3:-}" in
                queue:work|queue:listen) ROLE=worker ;;
                schedule:work) ROLE=scheduler ;;
            esac
        fi
        ;;
esac

# The public upload directory is a bind mount: image-time ownership does not apply.
mkdir -p "$APP_DIR/storage/app/public/products"
chown www-data:www-data "$APP_DIR/storage/app/public" "$APP_DIR/storage/app/public/products"

# Imagens dos sites das empresas (disco privado, servidas só quando referenciadas): também um volume persistente.
mkdir -p "$APP_DIR/storage/app/private/websites"
chown www-data:www-data "$APP_DIR/storage/app/private/websites"

# O build do front fica num volume compartilhado, lido pelo Nginx. Só o container web publica nele:
# worker e scheduler não servem assets, e três cópias simultâneas no mesmo volume são desnecessárias.
# Um comando avulso só copia quando não há build algum (container sem o volume), para não publicar
# assets de uma imagem que ainda não foi implantada.
mkdir -p "$APP_DIR/public/build"
if [ "$ROLE" = "web" ] || { [ "$ROLE" = "oneoff" ] && [ ! -f "$APP_DIR/public/build/manifest.json" ]; }; then
    cp -a /opt/public-build/. "$APP_DIR/public/build/"
fi

if [ "$ROLE" != "oneoff" ]; then
    # Variáveis sem as quais a aplicação sobe, mas não funciona. Só os nomes vão para o log, nunca os valores.
    MISSING=""
    for VAR in APP_KEY APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD PLATFORM_CENTRAL_HOST; do
        eval "VALUE=\${$VAR:-}"
        [ -n "$VALUE" ] || MISSING="$MISSING $VAR"
    done
    [ -z "$MISSING" ] || fail "variáveis obrigatórias ausentes ou vazias:$MISSING"

    # Caches do Laravel, gerados a cada início com as variáveis reais do container. bootstrap/cache e
    # storage/framework ficam no sistema de arquivos de cada container (não há volume compartilhado), então
    # web, worker e scheduler geram cada um o seu, sem disputa de escrita. Os arquivos antigos saem antes:
    # se a geração falhar, o container não segue com um cache de outra configuração.
    rm -f bootstrap/cache/config.php bootstrap/cache/routes-v7.php
    for STEP in config:cache route:cache view:cache; do
        # Como www-data, para o PHP-FPM conseguir ler e renovar o que foi gerado.
        if ! OUTPUT=$(runuser -u www-data -- php artisan "$STEP" --no-ansi --no-interaction 2>&1); then
            log "$STEP falhou:"
            echo "$OUTPUT" | tail -n 20 >&2
            fail "$ROLE não inicia sem os caches do Laravel"
        fi
    done

    for FILE in bootstrap/cache/config.php bootstrap/cache/routes-v7.php; do
        [ -s "$FILE" ] || fail "cache não gerado: $FILE"
    done

    # O cache precisa refletir este container, não uma configuração herdada.
    CACHED_ENV=$(php -r '$c = require "bootstrap/cache/config.php"; echo $c["app"]["env"] ?? "";')
    [ "$CACHED_ENV" = "${APP_ENV:-production}" ] || fail "config em cache com APP_ENV divergente do container"

    log "$ROLE: caches de config, rotas e views gerados (APP_ENV=$CACHED_ENV)"
fi

exec "$@"
