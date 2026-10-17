#!/bin/sh
# The documents of the feature (checks C76, C82, C83 and C85): sh load-tests/tests/docs.sh --only <case>.
. "$(dirname "$0")/common.sh"

LOAD_README=load-tests/README.md
# What a run measures. Recording a row (load-tests/lib/baseline.js and the READMEs) is not part of it.
MEASURED="backend docker Makefile load-tests/docker-compose.yml load-tests/docker load-tests/main.js load-tests/lib load-tests/flows load-tests/scenarios"
# The merge of the HEL-14 (PR #19) into main, the commit that closes the feature: its first parent is main
# before the feature, its second the tip of the feature branch. Later changes to the measured code are not
# the feature's, so the rows are held against this commit and not against HEAD.
HEL14_MERGE=746797a639c16273b776b16252fb842707e38378
PROFILES="smoke load stress spike breakpoint soak contention limits"

has() {
    grep -qF -- "$2" "$1" || fail "$1 não cita: $2"
}

case "$CASE" in
    baseline-rows)
        HEADER='| Data | Commit | Máquina | Perfil | VUs ou taxa | req/s | p95 vitrine (ms) | p95 POST /api/orders (ms) | p95 order_payable_wait (ms) | Erro inesperado | Ponto de ruptura | Mudou desde a série anterior |'
        has "$LOAD_README" "$HEADER"
        ok "a tabela tem as 12 colunas"
        git merge-base --is-ancestor "$HEL14_MERGE" HEAD || fail "o merge $HEL14_MERGE da HEL-14 não é ancestral de HEAD"
        for profile in load breakpoint; do
            ROW=$(grep -E "^\| [0-9]{4}-[0-9]{2}-[0-9]{2} \| [0-9a-f]{7,40} \| [^|]+ \| $profile \|" "$LOAD_README" | head -1)
            [ -n "$ROW" ] || fail "nenhuma linha do $profile"
            [ "$(echo "$ROW" | awk -F '|' '{ print NF }')" -eq 14 ] || fail "a linha do $profile não tem 12 colunas"
            SHA=$(echo "$ROW" | awk -F '|' '{ gsub(/ /, "", $3); print $3 }')
            git merge-base --is-ancestor "$SHA" "$HEL14_MERGE^2" || fail "$SHA não é da branch da HEL-14"
            git merge-base --is-ancestor "$SHA" "$HEL14_MERGE^1" && fail "$SHA já estava na main antes da HEL-14"
            # shellcheck disable=SC2086
            git diff --quiet "$SHA" "$HEL14_MERGE" -- $MEASURED ':(exclude)load-tests/lib/baseline.js' || fail "o código medido mudou entre $SHA e o merge da HEL-14"
            ok "linha do $profile no commit $SHA, da HEL-14, e o código medido não mudou até o merge $HEL14_MERGE"
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
        has "$LOAD_README" 'Uma linha só entra na tabela quando duas rodadas seguidas do mesmo perfil, no mesmo commit, têm o p95 a menos de 20% uma da outra.'
        has "$LOAD_README" 'make load-down'
        ok "load-tests/README.md explica os perfis, os limites e o aceite"
        ;;
    *)
        unknown_case "baseline-rows readme agents load-readme"
        ;;
esac
