# Shared by the proof scripts of load-tests/tests (sourced, never run on its own).
# Each script runs from the repository root and takes `--only <case>`.
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT" || exit 1

LOAD="docker compose -f load-tests/docker-compose.yml"
DEV="docker compose"
RESULTS="load-tests/results"
UNLIMITED="load stress spike breakpoint soak contention"

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

# `--only <case>` -> CASE
CASE=""
while [ $# -gt 0 ]; do
    case "$1" in
        --only) CASE="$2"; shift 2 ;;
        *) shift ;;
    esac
done

psql_load() {
    $LOAD exec -T db psql -U ecommerce -d ecommerce_load -tAc "$1"
}

psql_dev() {
    $DEV exec -T db psql -U ecommerce -d ecommerce -tAc "$1"
}

unknown_case() {
    fail "caso desconhecido '$CASE'; casos: $1"
}
