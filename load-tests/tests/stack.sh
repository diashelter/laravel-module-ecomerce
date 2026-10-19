#!/bin/sh
# Inspects the running load stack (checks C9, C12, C17 to C19, C22, C23, C25 and C26):
# sh load-tests/tests/stack.sh --only <case>. A run leaves the stack up.
. "$(dirname "$0")/common.sh"

env_of() {
    $LOAD exec -T "$1" printenv "$2" || true
}

last_profile() {
    ls -t "$RESULTS"/*-checks.txt 2>/dev/null | head -1 | sed -e 's|.*/||' -e 's|-checks.txt$||'
}

case "$CASE" in
    connection)
        grep -qx 'name: ecommerce-load' load-tests/docker-compose.yml || fail "o projeto não se chama ecommerce-load"
        expect_eq "os serviços" "$($LOAD --profile k6 config --services | sort | tr '\n' ' ')" "api db event-consumer k6 nginx outbox-relay queue-worker rabbitmq redis "
        expect_eq "DB_HOST" "$(env_of api DB_HOST)" db
        expect_eq "DB_DATABASE" "$(env_of api DB_DATABASE)" ecommerce_load
        expect_eq "REDIS_HOST" "$(env_of api REDIS_HOST)" redis
        expect_eq "RABBITMQ_HOST" "$(env_of api RABBITMQ_HOST)" rabbitmq
        expect_eq "SESSION_DOMAIN" "$(env_of api SESSION_DOMAIN)" null
        expect_eq "SANCTUM_STATEFUL_DOMAINS" "$(env_of api SANCTUM_STATEFUL_DOMAINS)" nginx
        expect_eq "APP_URL" "$(env_of api APP_URL)" http://nginx
        expect_eq "o Postgres de dev não tem ecommerce_load" "$(psql_dev "select count(*) from pg_database where datname = 'ecommerce_load'")" 0
        ;;
    reports)
        HTML=$(ls -t "$RESULTS"/smoke-*.html 2>/dev/null | head -1)
        [ -n "$HTML" ] || fail "nenhum relatório HTML do smoke"
        STAMP=$(basename "$HTML" .html | sed 's/^smoke-//')
        echo "$STAMP" | grep -Eq '^[0-9]{8}-[0-9]{6}$' || fail "carimbo fora do formato: $STAMP"
        [ -f "$RESULTS/smoke-$STAMP.json" ] || fail "falta o JSON smoke-$STAMP.json"
        ok "smoke-$STAMP.html e .json"
        expect_eq "nada de results/ no git status" "$(git status --porcelain "$RESULTS")" ""
        git check-ignore -q "$HTML" || fail "o HTML não é ignorado"
        git check-ignore -q "$RESULTS/smoke-$STAMP.json" || fail "o JSON não é ignorado"
        ok "os relatórios são ignorados pelo git"
        ;;
    app-settings)
        ABOUT=$($LOAD exec -T api php artisan about --json)
        echo "$ABOUT" | grep -q '"environment":"production"' || fail "environment não é production"
        echo "$ABOUT" | grep -q '"debug_mode":false' || fail "debug_mode não é false"
        echo "$ABOUT" | grep -q '"logs":"stderr"' || fail "os logs não são stderr"
        ok "production, sem debug, logs em stderr"
        expect_eq "LOG_LEVEL" "$(env_of api LOG_LEVEL)" warning
        ;;
    opcache-and-caches)
        expect_eq "opcache.validate_timestamps" "$($LOAD exec -T api php -r 'echo ini_get("opcache.validate_timestamps");')" 0
        for file in config.php routes-v7.php events.php; do
            $LOAD exec -T api test -f "/tmp/laravel/$file" || fail "/tmp/laravel/$file não existe"
            $LOAD exec -T api test ! -f "/var/www/backend/bootstrap/cache/$file" || fail "bootstrap/cache/$file existe"
        done
        ok "caches em /tmp/laravel e nenhum em bootstrap/cache"
        ;;
    fpm-pool)
        POOL=$($LOAD exec -T api php-fpm -tt 2>&1)
        echo "$POOL" | grep -Eq 'pm = static$' || fail "pm não é static"
        echo "$POOL" | grep -Eq 'pm.max_children = 16$' || fail "pm.max_children não é 16"
        ok "pm = static, pm.max_children = 16"
        ;;
    limiter-env)
        PROFILE=$(last_profile)
        [ -n "$PROFILE" ] || fail "nenhuma rodada em results/"
        case " $UNLIMITED " in
            *" $PROFILE "*) EXPECTED=array ;;
            *) EXPECTED="" ;;
        esac
        expect_eq "CACHE_LIMITER depois do $PROFILE" "$(env_of api CACHE_LIMITER)" "$EXPECTED"
        ;;
    bcrypt-and-delivery)
        expect_eq "BCRYPT_ROUNDS" "$(env_of api BCRYPT_ROUNDS)" 12
        expect_eq "ORDER_DELIVERY_DELAY_SECONDS" "$(env_of api ORDER_DELIVERY_DELAY_SECONDS)" 10
        expect_eq "a config em cache usa o mesmo atraso" "$($LOAD exec -T api php artisan tinker --execute="echo config('shop.delivery_delay_seconds');" | tr -d '[:space:]')" 10
        ;;
    cache-env)
        for service in api queue-worker outbox-relay event-consumer; do
            expect_eq "$service APP_CONFIG_CACHE" "$(env_of $service APP_CONFIG_CACHE)" /tmp/laravel/config.php
            expect_eq "$service APP_ROUTES_CACHE" "$(env_of $service APP_ROUTES_CACHE)" /tmp/laravel/routes-v7.php
            expect_eq "$service APP_EVENTS_CACHE" "$(env_of $service APP_EVENTS_CACHE)" /tmp/laravel/events.php
            expect_eq "$service LOG_CHANNEL" "$(env_of $service LOG_CHANNEL)" stderr
            expect_eq "$service LOG_LEVEL" "$(env_of $service LOG_LEVEL)" warning
        done
        ;;
    k6-image)
        grep -Eq '^ *image: grafana/k6:2\.3\.[0-9]+$' load-tests/docker-compose.yml || fail "a imagem do k6 não é uma tag exata da 2.3"
        ok "grafana/k6 com tag exata da 2.3"
        grep -q 'K6_WEB_DASHBOARD=true' Makefile || fail "o Makefile não liga o K6_WEB_DASHBOARD"
        grep -q 'K6_WEB_DASHBOARD_EXPORT=results/' Makefile || fail "o relatório HTML não vai para results/"
        ok "relatório HTML em results/"
        ;;
    no-host-ports)
        $LOAD --profile k6 config | grep -q 'published:' && fail "um serviço de carga publica porta no host"
        ok "nenhuma porta publicada no host"
        ;;
    dev-compose-unchanged)
        # The range of the feature, from main before it to its merge: main and HEAD keep moving after the merge.
        git merge-base --is-ancestor "$HEL14_MERGE" HEAD || fail "o merge $HEL14_MERGE da HEL-14 não é ancestral de HEAD"
        git diff --quiet "$HEL14_MERGE^1" "$HEL14_MERGE" -- docker-compose.yml docker/ || fail "a HEL-14 mudou o Compose de dev"
        ok "o Compose de dev não mudou entre $HEL14_MERGE^1 e o merge $HEL14_MERGE da HEL-14"
        ;;
    *)
        unknown_case "connection reports app-settings opcache-and-caches fpm-pool limiter-env bcrypt-and-delivery cache-env k6-image no-host-ports dev-compose-unchanged"
        ;;
esac
