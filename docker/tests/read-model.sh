#!/bin/sh
# Proofs of the environment of the storefront read model: the Makefile targets, the Compose files, the Nginx gateway
# and the CI workflow. Run from anywhere: sh docker/tests/read-model.sh --only <case>.
# Each case exits with 1 and says what failed. The cases that stop containers need the dev stack up (make up) and leave
# it as they found it.
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT" || exit 1

DEV="docker compose"
LOAD="docker compose -f load-tests/docker-compose.yml"

fail() {
    echo "FALHOU: $*" >&2
    exit 1
}

ok() {
    echo "ok: $*"
}

expect_eq() {
    [ "$2" = "$3" ] || fail "$1: esperado '$3', obtido '$2'"
    ok "$1"
}

has() {
    grep -qF -- "$2" "$1" || fail "$1 não cita: $2"
}

# Line number of the first line of a text that contains a string (empty when none).
line_of() {
    printf '%s\n' "$1" | grep -nF -- "$2" | head -1 | cut -d: -f1
}


APP_PORT=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2)
BASE_URL="http://localhost:${APP_PORT:-8080}"

# The value at a path of the resolved Compose file: `service <compose command> <service> <python expression on s>`.
service() {
    $1 config --format json | python3 -c '
import json, sys
s = json.load(sys.stdin)["services"]["'"$2"'"]
print('"$3"')
'
}

status_of() {
    curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$@"
}

wait_status() {
    # wait_status <expected> <curl args>: up to 40 seconds.
    n=0
    while [ "$n" -lt 40 ]; do
        [ "$(status_of "${@:2}" 2>/dev/null)" = "$1" ] && return 0
        n=$((n + 1)); sleep 1
    done
    return 1
}

# The lines of the location block that starts with the given header, in a Nginx file.
block() {
    python3 - "$1" "$2" <<'PY'
import sys
text = open(sys.argv[1]).read()
start = text.index(sys.argv[2])
depth = 0
for i in range(text.index("{", start), len(text)):
    depth += text[i] == "{"
    depth -= text[i] == "}"
    if depth == 0:
        print(text[start:i + 1])
        break
PY
}

first_product_id() {
    curl -s --max-time 20 "$BASE_URL/api/products" | python3 -c 'import json, sys; print(json.load(sys.stdin)["data"][0]["id"])'
}

up_dev() {
    $DEV up -d api api-read nginx >/dev/null 2>&1
}

CASE=""
while [ $# -gt 0 ]; do
    case "$1" in
        --only)
            [ $# -ge 2 ] || { echo "FALHOU: --only sem valor" >&2; exit 1; }
            CASE="$2"
            shift 2
            ;;
        *)
            echo "FALHOU: opção desconhecida $1" >&2
            exit 1
            ;;
    esac
done

case "$CASE" in
    make-rebuild)
        # The three commands, in this order and only them; no module calls a command of the other.
        recipe=$(make -n storefront-rebuild 2>&1) || fail "make -n storefront-rebuild falhou: $recipe"
        commands=$(printf '%s\n' "$recipe" | grep -o 'php artisan .*' | sed 's/^php artisan //')
        expect_eq "os comandos do storefront-rebuild" "$commands" "catalog:read-model-clear
catalog:republish
inventory:republish"
        if grep -rnE "['\"]inventory:[a-z-]+['\"]" backend/app-modules/catalog; then
            fail "um arquivo do catalog chama um comando inventory:"
        fi
        if grep -rnE "['\"]catalog:[a-z-]+['\"]" backend/app-modules/inventory; then
            fail "um arquivo do inventory chama um comando catalog:"
        fi
        ok "nenhum módulo chama o comando do outro"
        ;;
    make-fresh-seed)
        for target in fresh seed; do
            recipe=$(make -n "$target" 2>&1) || fail "make -n $target falhou: $recipe"
            if [ "$target" = fresh ]; then first="migrate:fresh --seed"; else first="db:seed"; fi
            migrate=$(line_of "$recipe" "$first")
            clear=$(line_of "$recipe" "catalog:read-model-clear")
            last=$(line_of "$recipe" "inventory:republish")
            [ -n "$migrate" ] && [ -n "$clear" ] && [ -n "$last" ] || fail "make $target não roda $first e a reconstrução"
            [ "$migrate" -lt "$clear" ] && [ "$clear" -lt "$last" ] || fail "make $target não reconstrói depois de $first"
            [ "$last" -eq "$(printf '%s\n' "$recipe" | grep -c .)" ] || fail "make $target não termina com a reconstrução"
            ok "make $target termina com a reconstrução"
        done
        grep -qE '^\s*\$\(MAKE\) storefront-rebuild' Makefile || fail "o Makefile não chama \$(MAKE) storefront-rebuild"
        ok "as receitas chamam \$(MAKE) storefront-rebuild"
        ;;

    gateway-storefront)
        # With the api stopped, the storefront still answers: it is served by api-read.
        id=$(first_product_id) || fail "a vitrine não tem produtos: rode make storefront-rebuild"
        trap 'up_dev' EXIT
        $DEV stop api >/dev/null 2>&1
        expect_eq "GET /api/products" "$(status_of "$BASE_URL/api/products")" 200
        expect_eq "GET /api/products?sort=price_desc&page=1" "$(status_of "$BASE_URL/api/products?sort=price_desc&page=1")" 200
        expect_eq "GET /api/products/{product}" "$(status_of "$BASE_URL/api/products/$id")" 200
        expect_eq "HEAD /api/products" "$(status_of -I "$BASE_URL/api/products")" 200
        ;;
    gateway-rest)
        # With api-read stopped, everything else answers from the api, as before.
        trap 'up_dev' EXIT
        $DEV stop api-read >/dev/null 2>&1
        expect_eq "GET /api/categories" "$(status_of "$BASE_URL/api/categories")" 200
        expect_eq "GET /api/admin/products sem sessão" "$(status_of "$BASE_URL/api/admin/products" -H 'Accept: application/json')" 401
        expect_eq "POST /api/cart/validate vazio" "$(status_of -X POST "$BASE_URL/api/cart/validate" -H 'Accept: application/json' -H 'Content-Type: application/json' -d '{}')" 422
        expect_eq "DELETE /api/products" "$(status_of -X DELETE "$BASE_URL/api/products" -H 'Accept: application/json')" 405
        expect_eq "GET /sanctum/csrf-cookie" "$(status_of "$BASE_URL/sanctum/csrf-cookie")" 204
        horizon=$(status_of "$BASE_URL/horizon")
        [ "$horizon" != 503 ] && [ "$horizon" != 502 ] || fail "/horizon respondeu $horizon"
        ok "/horizon respondeu $horizon, pelo api"
        ;;
    gateway-down)
        trap 'up_dev' EXIT
        $DEV stop api-read >/dev/null 2>&1
        headers=$(curl -s -D - -o /tmp/read-model-body.$$ --max-time 20 "$BASE_URL/api/products")
        expect_eq "o status" "$(printf '%s' "$headers" | head -1 | cut -d' ' -f2)" 503
        printf '%s' "$headers" | grep -qi '^content-type: application/json' || fail "o Content-Type não é application/json"
        header_id=$(printf '%s' "$headers" | grep -i '^x-request-id:' | tr -d '\r' | cut -d' ' -f2)
        python3 - "$header_id" /tmp/read-model-body.$$ <<'PY' || fail "o corpo do 503 não é o padrão"
import json, sys
body = json.load(open(sys.argv[2]))
assert body == {"code": "SERVICE_UNAVAILABLE", "message": "Este recurso está indisponível no momento.", "errors": {}, "request_id": sys.argv[1]}, body
assert sys.argv[1], "X-Request-ID vazio"
PY
        rm -f /tmp/read-model-body.$$
        ok "o corpo e o X-Request-ID do 503"
        # The storefront block of both gateways has one fastcgi_pass, to api-read; the error page has none.
        for conf in docker/nginx/default.conf load-tests/docker/nginx.conf; do
            storefront=$(block "$conf" 'location ~ ^/api/products')
            expect_eq "$conf: fastcgi_pass da vitrine" "$(printf '%s\n' "$storefront" | grep -c 'fastcgi_pass')" 1
            printf '%s\n' "$storefront" | grep -q 'api-read:9000' || fail "$conf: a vitrine não vai para api-read"
            printf '%s\n' "$storefront" | grep 'fastcgi_pass' | grep -q 'api:9000' && fail "$conf: a vitrine cita o api"
            unavailable=$(block "$conf" 'location @storefront_unavailable')
            [ "$(printf '%s\n' "$unavailable" | grep -c 'fastcgi_pass')" -eq 0 ] || fail "$conf: a página de erro tem fastcgi_pass"
            ok "$conf: a página de erro não tem fastcgi_pass"
        done
        ;;
    gateway-cold)
        # api-read is down before the Nginx (re)starts: the Nginx starts and answers 503.
        trap 'up_dev; $DEV up -d --force-recreate nginx >/dev/null 2>&1' EXIT
        $DEV stop api-read >/dev/null 2>&1
        $DEV up -d --force-recreate --no-deps nginx >/dev/null 2>&1
        wait_status 503 "$BASE_URL/api/products" || fail "o Nginx não respondeu 503 com o api-read parado"
        body=$(curl -s --max-time 20 "$BASE_URL/api/products")
        printf '%s' "$body" | python3 -c 'import json, sys; b = json.load(sys.stdin); assert b["code"] == "SERVICE_UNAVAILABLE" and b["errors"] == {}' || fail "o corpo do 503 não é o padrão"
        ok "o Nginx subiu sem o api-read e respondeu 503"
        ;;
    api-read-no-db)
        for stack in "$DEV" "$LOAD"; do
            for variable in DB_HOST DB_USERNAME DB_PASSWORD; do
                expect_eq "$stack: $variable do api-read" "$(service "$stack" api-read 's["environment"].get("'$variable'", "<ausente>")')" ""
            done
            expect_eq "$stack: api-read não depende do db" "$(service "$stack" api-read '"db" in s.get("depends_on", {})')" False
        done
        id=$(first_product_id) || fail "a vitrine não tem produtos: rode make storefront-rebuild"
        trap '$DEV start db >/dev/null 2>&1' EXIT
        $DEV stop db >/dev/null 2>&1
        expect_eq "a vitrine com o db parado" "$(status_of "$BASE_URL/api/products/$id")" 200
        ;;
    client-address)
        for conf in docker/nginx/default.conf load-tests/docker/nginx.conf; do
            for header in 'location ~ ^/api/products' 'location @laravel' 'location ~ ^/(api|sanctum'; do
                section=$(block "$conf" "$header")
                printf '%s\n' "$section" | grep -q 'include fastcgi_params;' || fail "$conf ($header) não inclui fastcgi_params"
                printf '%s\n' "$section" | grep -q 'REMOTE_ADDR' && fail "$conf ($header) sobrescreve REMOTE_ADDR"
            done
            ok "$conf: fastcgi_params sem REMOTE_ADDR nos blocos FastCGI"
        done
        grep -rnEi 'trustProxies\(|trustedProxies' backend/bootstrap backend/app backend/app-modules/*/src | grep -v '//' && fail "o Laravel confia em algum proxy"
        ok "o Laravel não confia em proxy: o X-Forwarded-For do cliente é ignorado"
        ;;
    test-mongo-db)
        main=$(make -n test-filter FILTER=x 2>/dev/null | grep 'MONGODB_DATABASE')
        printf '%s' "$main" | grep -q -- '-e MONGODB_DATABASE=ecommerce_testing ' || fail "o make test não passa MONGODB_DATABASE=ecommerce_testing: $main"
        ok "o make test usa o banco do MongoDB do checkout"
        probe=ecommerce_testing_probe_$$
        $DEV exec -T -e P="$probe" mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "db.getSiblingDB(process.env.P).t.insertOne({a: 1})"' >/dev/null || fail "não criei o banco de sondagem"
        make test-db-drop TEST_DB_DATABASE="$probe" >/dev/null 2>&1 || fail "make test-db-drop falhou"
        left=$($DEV exec -T -e P="$probe" mongodb sh -c 'mongosh --quiet -u "$MONGO_INITDB_ROOT_USERNAME" -p "$MONGO_INITDB_ROOT_PASSWORD" --authenticationDatabase admin --eval "db.getMongo().getDBNames().includes(process.env.P)"')
        expect_eq "o banco de sondagem depois do make test-db-drop" "$left" false
        ;;
    ci)
        python3 - <<'PY' || fail "o CI não sobe o mongodb"
import re
text = open(".github/workflows/ci.yml").read()
backend = text.split("load-smoke:")[0]
assert re.search(r"docker compose up -d --wait db redis rabbitmq mongodb", backend), "o job backend não sobe o mongodb com --wait"
smoke = text.split("load-smoke:")[1]
assert "make load-test TYPE=smoke" in smoke, "o job load-smoke não roda o smoke"
PY
        ok "o job backend sobe o mongodb com --wait e o load-smoke roda o smoke"
        for name in mongodb api-read; do
            [ "$(service "$LOAD" "$name" '"image" in s')" = True ] || fail "a pilha de carga não tem o $name"
        done
        ok "a pilha de carga tem o mongodb e o api-read"
        ;;
    dev-services)
        expect_eq "a imagem do mongodb" "$(service "$DEV" mongodb 's["image"]')" "mongo:8.2"
        expect_eq "o healthcheck do mongodb" "$(service "$DEV" mongodb '"healthcheck" in s')" True
        expect_eq "o usuário raiz" "$(service "$DEV" mongodb '"MONGO_INITDB_ROOT_USERNAME" in s["environment"] and "MONGO_INITDB_ROOT_PASSWORD" in s["environment"]')" True
        for name in api queue-worker outbox-relay event-consumer scheduler api-read; do
            expect_eq "$name depende do mongodb saudável" "$(service "$DEV" "$name" 's["depends_on"]["mongodb"]["condition"]')" service_healthy
        done
        grep -q '"mongodb/laravel-mongodb": "^5.7' backend/composer.json || fail "o composer.json não exige mongodb/laravel-mongodb ^5.7"
        ok "o composer.json exige mongodb/laravel-mongodb ^5.7"
        grep -q 'pecl install .*mongodb' docker/php/Dockerfile || fail "o Dockerfile não instala a extensão mongodb"
        ok "o Dockerfile instala a extensão mongodb"
        ;;
    *)
        echo "FALHOU: caso desconhecido '$CASE'" >&2
        exit 1
        ;;
esac
