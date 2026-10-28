#!/bin/sh
# The documents of the feature (checks C76, C82, C83 and C85, and C62 of the HEL-13): sh load-tests/tests/docs.sh --only <case>.
. "$(dirname "$0")/common.sh"

LOAD_README=load-tests/README.md
# What a run measures. Recording a row (load-tests/lib/baseline.js and the READMEs) is not part of it.
MEASURED="backend docker Makefile load-tests/docker-compose.yml load-tests/docker load-tests/main.js load-tests/lib load-tests/flows load-tests/scenarios"
# The rows are held against HEL14_MERGE (common.sh) and not against HEAD (AC 71): later commits, the feature's
# own follow-ups included, are compared with the rows and do not reopen them.
PROFILES="smoke load stress spike breakpoint soak contention limits"

has() {
    grep -qF -- "$2" "$1" || fail "$1 não cita: $2"
}

case "$CASE" in
    read-model)
        # The storefront read model (HEL-17): the series, the metric, the team product and the 202 products.
        has "$LOAD_README" 'a vitrine passou a ser servida pelo `api-read`'
        ok "o README diz que a vitrine passou a ser servida pelo api-read"
        for table_header in '| Data | Commit | Máquina | Perfil |' '| Data | Commit | Máquina | Transporte |'; do
            START=$(grep -nF -- "$table_header" "$LOAD_README" | head -1 | cut -d: -f1)
            [ -n "$START" ] || fail "tabela sem cabeçalho: $table_header"
            tail -n +"$START" "$LOAD_README" | awk 'NR > 2 && /^\|/ { print } NR > 2 && !/^\|/ { exit }' | grep -qF 'série nova: a vitrine passou a ser servida pelo `api-read`' || fail "a tabela '$table_header' não abre a série nova"
            ok "a tabela '$table_header' abre a série nova"
        done
        has "$LOAD_README" 'storefront_projection_lag'
        has "$LOAD_README" 'Produto da Equipe'
        has "$LOAD_README" '202 produtos'
        grep -q '201 produtos' "$LOAD_README" && fail "o README ainda cita 201 produtos"
        ok "a métrica, o Produto da Equipe e os 202 produtos, sem 201"
        ;;
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
    transport-rows)
        # The second table (HEL-13, after the one of the HEL-14): the same baseline with the transport of the relay.
        HEADER='| Data | Commit | Máquina | Transporte | Perfil | VUs ou taxa | req/s | p95 vitrine (ms) | p95 POST /api/orders (ms) | p95 order_payable_wait (ms) | Erro inesperado | Mudou desde a série anterior |'
        has "$LOAD_README" "$HEADER"
        FIRST=$(grep -nF -- '| Data | Commit | Máquina | Perfil |' "$LOAD_README" | head -1 | cut -d: -f1)
        SECOND=$(grep -nF -- "$HEADER" "$LOAD_README" | head -1 | cut -d: -f1)
        [ "$FIRST" -lt "$SECOND" ] || fail "a tabela por transporte não vem depois da tabela da HEL-14"
        ok "a tabela por transporte vem depois da tabela da HEL-14"
        SHAS=""
        for transport in redis rabbitmq; do
            ROW=$(grep -E "^\| [0-9]{4}-[0-9]{2}-[0-9]{2} \| [0-9a-f]{7,40} \| [^|]+ \| $transport \| load \|" "$LOAD_README" | head -1)
            [ -n "$ROW" ] || fail "nenhuma linha load do transporte $transport"
            [ "$(echo "$ROW" | awk -F '|' '{ print NF }')" -eq 14 ] || fail "a linha do $transport não tem 12 colunas"
            SHA=$(echo "$ROW" | awk -F '|' '{ gsub(/ /, "", $3); print $3 }')
            git merge-base --is-ancestor "$SHA" HEAD || fail "$SHA não é ancestral de HEAD"
            echo "$ROW" | grep -q "20%" || fail "a nota da linha do $transport não cita a regra dos 20%"
            echo "$ROW" | grep -q "rodada anterior do $transport" || fail "a nota da linha do $transport não cita a rodada anterior do mesmo transporte"
            SHAS="$SHAS $SHA"
            ok "linha load do $transport no commit $SHA, aceita pela regra dos 20%"
        done
        [ "$(echo "$SHAS" | tr ' ' '\n' | sort -u | grep -c .)" -eq 1 ] || fail "as duas linhas não são do mesmo commit:$SHAS"
        ok "as duas linhas são do mesmo commit"
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
        unknown_case "read-model baseline-rows transport-rows readme agents load-readme"
        ;;
esac
