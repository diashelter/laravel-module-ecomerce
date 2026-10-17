# Shared by the proof scripts of load-tests/tests (sourced, never run on its own).
# Each script runs from the repository root and takes `--only <case>`.
ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT" || exit 1

LOAD="docker compose -f load-tests/docker-compose.yml"
DEV="docker compose"
RESULTS="load-tests/results"
UNLIMITED="load stress spike breakpoint soak contention"
# The merge of the HEL-14 (PR #19) into main: its first parent is main before the feature, its second the tip
# of the feature branch. The proofs about the feature as a whole are held against it, never against main or
# HEAD, which keep moving after it (checks C26 and C76).
HEL14_MERGE=746797a639c16273b776b16252fb842707e38378

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

# `--only <case>` -> CASE. An option without its value is an error, never an endless loop, and an unknown one
# is an error too, so a misspelled option never leaves a proof without its assertion.
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

psql_load() {
    $LOAD exec -T db psql -U ecommerce -d ecommerce_load -tAc "$1"
}

psql_dev() {
    $DEV exec -T db psql -U ecommerce -d ecommerce -tAc "$1"
}

unknown_case() {
    fail "caso desconhecido '$CASE'; casos: $1"
}
