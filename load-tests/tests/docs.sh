#!/bin/sh
# The documents of the feature (checks C76, C82, C83 and C85): sh load-tests/tests/docs.sh --only <case>.
. "$(dirname "$0")/common.sh"

LOAD_README=load-tests/README.md
# What a run measures. Recording a row (load-tests/lib/baseline.js and the READMEs) is not part of it.
MEASURED="backend docker Makefile load-tests/docker-compose.yml load-tests/docker load-tests/main.js load-tests/lib load-tests/flows load-tests/scenarios"
PROFILES="smoke load stress spike breakpoint soak contention limits"

has() {
    grep -qF -- "$2" "$1" || fail "$1 não cita: $2"
}

case "$CASE" in
    baseline-rows)
        HEADER='| Data | Commit | Máquina | Perfil | VUs ou taxa | req/s | p95 vitrine (ms) | p95 POST /api/orders (ms) | p95 order_payable_wait (ms) | Erro inesperado | Ponto de ruptura | Mudou desde a série anterior |'
        has "$LOAD_README" "$HEADER"
        ok "a tabela tem as 12 colunas"
        for profile in load breakpoint; do
            ROW=$(grep -E "^\| [0-9]{4}-[0-9]{2}-[0-9]{2} \| [0-9a-f]{7,40} \| [^|]+ \| $profile \|" "$LOAD_README" | head -1)
            [ -n "$ROW" ] || fail "nenhuma linha do $profile"
            [ "$(echo "$ROW" | awk -F '|' '{ print NF }')" -eq 14 ] || fail "a linha do $profile não tem 12 colunas"
            SHA=$(echo "$ROW" | awk -F '|' '{ gsub(/ /, "", $3); print $3 }')
            git merge-base --is-ancestor "$SHA" HEAD || fail "$SHA não é ancestral de HEAD"
            git merge-base --is-ancestor "$SHA" origin/main && fail "$SHA já está na main"
            # shellcheck disable=SC2086
            git diff --quiet "$SHA" HEAD -- $MEASURED ':(exclude)load-tests/lib/baseline.js' || fail "o código medido mudou depois de $SHA"
            ok "linha do $profile no commit $SHA, e o código medido não mudou até HEAD"
        done
        ;;
    readme)
        has README.md '## Testes de carga (k6)'
        has README.md '[Testes de carga (k6)](#testes-de-carga-k6)'
        has README.md '(load-tests/README.md)'
        for profile in $PROFILES; do
            has README.md "\`$profile\`"
        done
        has README.md 'make load-test'
        has README.md 'make load-down'
        has README.md 'CACHE_LIMITER'
        has README.md 'limites de aprovação'
        has README.md 'ecommerce-load'
        ok "o README descreve os testes de carga"
        ;;
    agents)
        has AGENTS.md 'ecommerce-load'
        has AGENTS.md 'bootstrap/cache'
        has AGENTS.md 'make load-test TYPE=smoke'
        ok "o AGENTS.md tem a regra e o checklist"
        ;;
    load-readme)
        for profile in $PROFILES; do
            has "$LOAD_README" "make load-test TYPE=$profile"
        done
        has "$LOAD_README" 'Limites de aprovação'
        has "$LOAD_README" 'a menos de 20%'
        has "$LOAD_README" 'make load-down'
        ok "load-tests/README.md explica os perfis, os limites e o aceite"
        ;;
    *)
        unknown_case "baseline-rows readme agents load-readme"
        ;;
esac
