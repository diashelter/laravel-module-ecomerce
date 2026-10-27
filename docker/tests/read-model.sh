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
    *)
        echo "FALHOU: caso desconhecido '$CASE'" >&2
        exit 1
        ;;
esac
