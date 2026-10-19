#!/bin/sh
# Proofs of the environment of the event transport (HEL-13): the Compose files, the image, the Makefile
# targets, the CI workflow and the documents. Run from anywhere: sh docker/tests/environment.sh --only <case>.
# Each case exits with 1 and says what failed. The cases that start containers need the dev stack up
# (db, redis, rabbitmq and api) and leave it up.
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT" || exit 1

DEV="docker compose"
LOAD="docker compose -f load-tests/docker-compose.yml"
TEST_FILTER="connects to the checkout vhost"

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

# The value at a path of the resolved Compose file: `service <compose command> <service> <python expression on s>`.
# The command is "docker compose" (dev) or the load one.
service() {
    $1 config --format json | python3 -c '
import json, sys
s = json.load(sys.stdin)["services"]["'"$2"'"]
print('"$3"')
'
}

vhosts() {
    $DEV exec -T rabbitmq rabbitmqctl -q list_vhosts --no-table-headers
}

has_vhost() {
    vhosts | grep -qx "$1"
}

db_exists() {
    $DEV exec -T db psql -U ecommerce -d ecommerce -tAc "select count(*) from pg_database where datname = '$1'"
}

case "$CASE" in
    dev-rabbitmq)
        expect_eq "a imagem" "$(service "$DEV" rabbitmq 's["image"]')" "rabbitmq:4.3.5-management-alpine"
        expect_eq "o healthcheck" "$(service "$DEV" rabbitmq '"healthcheck" in s')" True
        expect_eq "o volume nomeado" "$(service "$DEV" rabbitmq '[v["source"] for v in s["volumes"] if v["target"] == "/var/lib/rabbitmq"][0]')" rabbitmq_data
        $DEV config --format json | python3 -c 'import json, sys; sys.exit(0 if "rabbitmq_data" in json.load(sys.stdin)["volumes"] else 1)' || fail "rabbitmq_data não é um volume nomeado"
        expect_eq "a porta do painel" "$(service "$DEV" rabbitmq '[p["target"] for p in s["ports"]]')" "[15672]"
        has docker-compose.yml '"${RABBITMQ_MANAGEMENT_PORT:-15672}:15672"'
        for variable in RABBITMQ_HOST RABBITMQ_PORT RABBITMQ_USER RABBITMQ_PASSWORD RABBITMQ_VHOST; do
            has backend/.env.example "$variable="
        done
        for variable in RABBITMQ_MANAGEMENT_PORT RABBITMQ_USER RABBITMQ_PASSWORD; do
            has .env.example "$variable="
        done
        has backend/.env.example "RABBITMQ_HOST=rabbitmq"
        has backend/.env.example "RABBITMQ_PORT=5672"
        has backend/.env.example "RABBITMQ_VHOST=/"
        ok "os .env.example"
        ;;
    test-vhost)
        has_vhost ecommerce_testing && { $DEV exec -T rabbitmq rabbitmqctl -q delete_vhost ecommerce_testing >/dev/null || fail "não apagou o vhost"; }
        has_vhost ecommerce_testing && fail "o vhost ecommerce_testing ainda existe"
        make test-filter FILTER="$TEST_FILTER" >/dev/null 2>&1 || fail "make test-filter FILTER=\"$TEST_FILTER\" falhou"
        has_vhost ecommerce_testing || fail "o vhost ecommerce_testing não existe depois do make test"
        ok "o make test criou o vhost ecommerce_testing e o teste passou nele"
        ;;
    worktree-vhost)
        NAME=vhostproof
        DIR=$(mktemp -d)/$NAME
        cleanup() {
            ( cd "$DIR" 2>/dev/null && make test-db-drop >/dev/null 2>&1 )
            git worktree remove --force "$DIR" >/dev/null 2>&1
            rm -rf "$(dirname "$DIR")"
        }
        trap cleanup EXIT INT TERM
        git worktree add --detach "$DIR" HEAD >/dev/null 2>&1 || fail "não criou o worktree"
        OUTPUT=$(cd "$DIR" && make test-filter FILTER="$TEST_FILTER" 2>&1) || { echo "$OUTPUT" >&2; fail "make test-filter no worktree falhou"; }
        echo "$OUTPUT" | grep -qF "vhost do RabbitMQ: ecommerce_testing_$NAME" || fail "a saída não mostra o vhost ecommerce_testing_$NAME"
        has_vhost "ecommerce_testing_$NAME" || fail "o vhost ecommerce_testing_$NAME não existe"
        ok "o worktree $NAME testou no vhost ecommerce_testing_$NAME"
        ;;
    test-db-drop)
        make test-prepare >/dev/null 2>&1 || fail "make test-prepare falhou"
        has_vhost ecommerce_testing || fail "o vhost ecommerce_testing não existe antes"
        make test-db-drop >/dev/null 2>&1 || fail "make test-db-drop falhou"
        has_vhost ecommerce_testing && fail "o vhost ecommerce_testing ainda existe"
        expect_eq "o banco ecommerce_testing" "$(db_exists ecommerce_testing)" 0
        make test-prepare >/dev/null 2>&1 || fail "make test-prepare não recriou"
        ok "o vhost e o banco de testes foram apagados (e recriados no fim)"
        ;;
    broker-down)
        OUTPUT=$($DEV exec -T -e RABBITMQ_PORT=1 api ./vendor/bin/pest --filter="declares the topology before the first message" 2>&1)
        [ $? -ne 0 ] || fail "o teste passou sem o broker"
        echo "$OUTPUT" | grep -q "RabbitMQ" || fail "a saída não cita RabbitMQ"
        echo "$OUTPUT" | grep -qi "skipped" && fail "um teste foi pulado"
        ok "sem o broker o teste falha, cita RabbitMQ e nada é pulado"
        ;;
    ci)
        WORKFLOW=.github/workflows/ci.yml
        UP=$(grep -n 'docker compose up -d --wait db redis rabbitmq' "$WORKFLOW" | head -1 | cut -d: -f1)
        TEST=$(grep -n 'run: make test$' "$WORKFLOW" | head -1 | cut -d: -f1)
        [ -n "$UP" ] || fail "nenhum passo sobe db, redis e rabbitmq com --wait"
        [ -n "$TEST" ] || fail "nenhum passo roda make test"
        [ "$UP" -lt "$TEST" ] || fail "o rabbitmq sobe depois do make test"
        ok "o job backend sobe o rabbitmq (linha $UP) antes do make test (linha $TEST)"
        ;;
    dependencies)
        python3 - <<'PY' || exit 1
import json, sys
composer = json.load(open("backend/composer.json"))["require"]
lock = {p["name"] for p in json.load(open("backend/composer.lock"))["packages"]}
wanted = {"laravel/pennant": "^1.26", "laravel/horizon": "^5.50", "php-amqplib/php-amqplib": "^3.7"}
for name, constraint in wanted.items():
    if composer.get(name) != constraint:
        print(f"FALHOU: composer.json exige {composer.get(name)} de {name}, esperado {constraint}", file=sys.stderr)
        sys.exit(1)
    if name not in lock:
        print(f"FALHOU: composer.lock não traz {name}", file=sys.stderr)
        sys.exit(1)
print("ok: composer.json e composer.lock trazem os três pacotes")
PY
        ;;
    load-rabbitmq)
        expect_eq "a imagem" "$(service "$LOAD" rabbitmq 's["image"]')" "rabbitmq:4.3.5-management-alpine"
        expect_eq "o healthcheck" "$(service "$LOAD" rabbitmq '"healthcheck" in s')" True
        ;;
    event-consumer-services)
        for compose in "$DEV" "$LOAD"; do
            expect_eq "a imagem do event-consumer" "$(service "$compose" event-consumer 's["image"]')" "$(service "$compose" api 's["image"]')"
            expect_eq "o volume do event-consumer" "$(service "$compose" event-consumer 'sorted(v["source"] for v in s["volumes"] if v["target"] == "/var/www/backend")')" "$(service "$compose" api 'sorted(v["source"] for v in s["volumes"] if v["target"] == "/var/www/backend")')"
            expect_eq "o comando" "$(service "$compose" event-consumer 's["command"]')" "['php', 'artisan', 'events:consume']"
            expect_eq "restart" "$(service "$compose" event-consumer 's["restart"]')" "unless-stopped"
        done
        for variable in APP_CONFIG_CACHE APP_ROUTES_CACHE APP_EVENTS_CACHE LOG_CHANNEL LOG_LEVEL; do
            expect_eq "$variable na pilha de carga" "$(service "$LOAD" event-consumer "s['environment']['$variable']")" "$(service "$LOAD" api "s['environment']['$variable']")"
        done
        ;;
    horizon-services)
        for compose in "$DEV" "$LOAD"; do
            expect_eq "o comando do queue-worker" "$(service "$compose" queue-worker 's["command"]')" "['php', 'artisan', 'horizon']"
        done
        ;;
    nginx-horizon)
        PORT=$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2)
        HEADERS=$(curl -s -o /dev/null -D - "http://localhost:${PORT:-8080}/horizon") || fail "o Nginx de dev não responde"
        echo "$HEADERS" | head -1 | grep -q ' 403' || fail "GET /horizon não respondeu 403: $(echo "$HEADERS" | head -1)"
        echo "$HEADERS" | grep -qi '^x-request-id:' || fail "a resposta não traz X-Request-ID: veio do frontend, não da aplicação"
        ok "GET /horizon pelo Nginx foi respondido pela aplicação PHP (403 com X-Request-ID)"
        ;;
    scheduler)
        expect_eq "a imagem do scheduler" "$(service "$DEV" scheduler 's["image"]')" "$(service "$DEV" api 's["image"]')"
        expect_eq "o volume do scheduler" "$(service "$DEV" scheduler 'sorted(v["source"] for v in s["volumes"] if v["target"] == "/var/www/backend")')" "$(service "$DEV" api 'sorted(v["source"] for v in s["volumes"] if v["target"] == "/var/www/backend")')"
        expect_eq "o comando" "$(service "$DEV" scheduler 's["command"]')" "['php', 'artisan', 'schedule:work']"
        expect_eq "restart" "$(service "$DEV" scheduler 's["restart"]')" "unless-stopped"
        ;;
    docs-readme)
        for term in 'event-transport' 'make event-transport' 'events:consume' 'event-consumer' 'domain_events' '.dead' 'make events-failed' 'make events-retry' '/horizon' 'scheduler' 'rabbitmq_data'; do
            has README.md "$term"
        done
        ok "o README cita o transporte da flag, o RabbitMQ, o consumidor, as mortas, o Horizon e o volume"
        ;;
    docs-agents)
        has AGENTS.md 'ordering.mark_order_as_paid'
        has AGENTS.md 'event-transport'
        has AGENTS.md 'rabbitmq'
        ok "o AGENTS.md cita o nome estável do consumidor, a flag e o rabbitmq"
        ;;
    docs-domain)
        LINE=$(grep -m1 'Última atualização' docs/domain-analysis.md)
        echo "$LINE" | grep -q 'HEL-13' || fail "a linha 'Última atualização' não cita a HEL-13: $LINE"
        grep -E '✅' docs/domain-analysis.md | grep -q 'HEL-13' || fail "nenhum problema ✅ Resolvido cita a HEL-13"
        ok "a análise de domínio cita a HEL-13 na última atualização e num problema resolvido"
        ;;
    docs-load)
        has load-tests/README.md 'TRANSPORT=rabbitmq'
        has load-tests/README.md '250 ms'
        ok "o load-tests/README.md cita o TRANSPORT e os 250 ms"
        ;;
    *)
        fail "caso desconhecido '$CASE'; casos: dev-rabbitmq test-vhost worktree-vhost test-db-drop broker-down ci dependencies load-rabbitmq event-consumer-services horizon-services nginx-horizon scheduler docs-readme docs-agents docs-domain docs-load"
        ;;
esac
