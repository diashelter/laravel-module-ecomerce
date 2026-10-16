#!/bin/sh
# Drives make load-test and make load-down (checks C10, C11, C13 to C15 and C22):
# sh load-tests/tests/make.sh --only <case>. The fails-on-* cases inject a fault into a backup of a file,
# run make and restore the file in a trap, even when they fail.
. "$(dirname "$0")/common.sh"

PROFILES="smoke load stress spike breakpoint soak contention limits"
OUTPUT="$RESULTS/make-output.log"
mkdir -p "$RESULTS"

load_containers() {
    docker ps -aq --filter label=com.docker.compose.project=ecommerce-load | wc -l | tr -d ' '
}

load_volumes() {
    docker volume ls -q --filter label=com.docker.compose.project=ecommerce-load | wc -l | tr -d ' '
}

refuses() {
    make load-down >/dev/null 2>&1
    mkdir -p "$RESULTS"
    if make load-test "$@" >"$OUTPUT" 2>&1; then
        fail "make load-test $* saiu com 0"
    fi
    for profile in $PROFILES; do
        grep -q "$profile" "$OUTPUT" || fail "a saída não lista o perfil $profile"
    done
    ok "make load-test $* recusou e listou os 8 perfis"
    expect_eq "nenhum container de carga" "$(load_containers)" 0
}

# Restores $1 from its backup when the script exits.
with_fault() {
    cp "$1" "$1.fault-backup"
    trap 'mv "'"$1"'.fault-backup" "'"$1"'"' EXIT INT TERM
}

case "$CASE" in
    missing-type)
        refuses
        ;;
    invalid-type)
        refuses TYPE=bogus
        ;;
    recreates)
        psql_load "insert into categories (id, name, slug, created_at, updated_at) values (gen_random_uuid(), 'Marcador de carga', 'marcador-de-carga', now(), now())" >/dev/null \
            || fail "a pilha de carga não está no ar: rode uma vez sh load-tests/tests/run.sh --only smoke"
        make load-test TYPE=smoke >"$OUTPUT" 2>&1 || true
        expect_eq "a categoria de marcação sumiu" "$(psql_load "select count(*) from categories where slug = 'marcador-de-carga'")" 0
        ;;
    fails-on-threshold)
        FLOW=load-tests/flows/storefront.js
        with_fault "$FLOW"
        sed -e "s/'storefront: product list is 200', send('GET', '\/api\/products', { query }), \[200\]/'storefront: product list is 200', send('GET', '\/api\/products', { query }), [299]/" "$FLOW.fault-backup" >"$FLOW"
        grep -q '\[299\]' "$FLOW" || fail "a falha não foi injetada"
        MARK=$(mktemp)
        if make load-test TYPE=smoke >"$OUTPUT" 2>&1; then
            fail "o smoke com a falha saiu com 0"
        fi
        ok "o smoke com a falha saiu com código diferente de zero"
        [ -n "$(find "$RESULTS" -name 'smoke-*.html' -newer "$MARK")" ] || fail "nenhum HTML novo"
        [ -n "$(find "$RESULTS" -name 'smoke-*.json' -newer "$MARK")" ] || fail "nenhum JSON novo"
        ok "o HTML e o JSON da rodada foram gravados"
        awk -F '\t' '$1 == "check" && $4 == "storefront: product list is 200" && $3 >= 1 { found = 1 } END { exit found ? 0 : 1 }' "$RESULTS/smoke-checks.txt" \
            || fail "o check com a falha não falhou"
        ok "a falha veio do check alterado"
        ;;
    fails-on-seed)
        SEEDER=backend/database/seeders/LoadTestSeeder.php
        with_fault "$SEEDER"
        sed -e "s/        gc_collect_cycles();/        throw new \\\\RuntimeException('load seed failed on purpose');/" "$SEEDER.fault-backup" >"$SEEDER"
        grep -q 'load seed failed on purpose' "$SEEDER" || fail "a falha não foi injetada"
        MARK=$(mktemp)
        sleep 1
        if make load-test TYPE=smoke >"$OUTPUT" 2>&1; then
            fail "a rodada com o seed quebrado saiu com 0"
        fi
        ok "a rodada saiu com código diferente de zero"
        grep -q 'load seed failed on purpose' "$OUTPUT" || fail "a saída não mostra a falha do seed"
        sed -n '/Logs da api de carga/,$p' "$OUTPUT" | grep -Eq '^api-1 +\|' || fail "a saída não imprimiu os logs da api de carga"
        ok "os logs da api de carga foram impressos"
        [ -z "$(find "$RESULTS" -name 'smoke-*' -newer "$MARK")" ] || fail "o k6 rodou e gravou relatório"
        ok "o k6 não rodou"
        ;;
    down)
        DEV_CONTAINERS=$($DEV ps -q | wc -l | tr -d ' ')
        DEV_VOLUMES=$(docker volume ls -q --filter label=com.docker.compose.project=ecommerce | sort | tr '\n' ' ')
        make load-down >"$OUTPUT" 2>&1 || fail "make load-down falhou"
        expect_eq "nenhum container de carga" "$(load_containers)" 0
        expect_eq "nenhum volume de carga" "$(load_volumes)" 0
        expect_eq "os containers de dev no ar" "$($DEV ps -q | wc -l | tr -d ' ')" "$DEV_CONTAINERS"
        expect_eq "os volumes de dev" "$(docker volume ls -q --filter label=com.docker.compose.project=ecommerce | sort | tr '\n' ' ')" "$DEV_VOLUMES"
        ;;
    limiter-mapping)
        for profile in $PROFILES; do
            LINE=$(make -n load-test TYPE="$profile" | grep 'docker compose -f load-tests/docker-compose.yml up -d api')
            case " $UNLIMITED " in
                *" $profile "*) echo "$LINE" | grep -q '^CACHE_LIMITER=array ' || fail "$profile sem CACHE_LIMITER=array" ;;
                *) echo "$LINE" | grep -q '^CACHE_LIMITER= ' || fail "$profile com CACHE_LIMITER definido" ;;
            esac
            ok "$profile"
        done
        ;;
    *)
        unknown_case "missing-type invalid-type recreates fails-on-threshold fails-on-seed down limiter-mapping"
        ;;
esac
