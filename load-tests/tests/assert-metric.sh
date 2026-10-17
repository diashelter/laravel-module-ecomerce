#!/bin/sh
# A custom metric of the last run of a profile:
# sh load-tests/tests/assert-metric.sh <profile> --name <metric> [--equals <n>] [--at-least <n>]
#   [--value-at-least <n>] [--value-at-most <n>].
# The count is the sum of a counter, the number of samples of a trend, or the passes and fails of a rate.
# The value is the p95 of a trend, otherwise the per-second rate of a counter or the rate of a rate (lib/summary.js).
PROFILE="$1"
NAME=""
EQUALS=""
AT_LEAST=""
VALUE_AT_LEAST=""
VALUE_AT_MOST=""
shift
while [ $# -gt 0 ]; do
    case "$1" in
        --name | --equals | --at-least | --value-at-least | --value-at-most)
            [ $# -ge 2 ] || { echo "FALHOU: $1 sem valor" >&2; exit 1; }
            case "$1" in
                --name) NAME="$2" ;;
                --equals) EQUALS="$2" ;;
                --at-least) AT_LEAST="$2" ;;
                --value-at-least) VALUE_AT_LEAST="$2" ;;
                --value-at-most) VALUE_AT_MOST="$2" ;;
            esac
            shift 2
            ;;
        *)
            echo "FALHOU: opção desconhecida $1" >&2
            exit 1
            ;;
    esac
done
. "$(dirname "$0")/common.sh"

FILE="$RESULTS/$PROFILE-checks.txt"
[ -f "$FILE" ] || fail "$FILE não existe: rode sh load-tests/tests/run.sh --only $PROFILE"

COUNT=$(awk -F '\t' -v name="$NAME" '$1 == "metric" && $2 == name { print $3 }' "$FILE")
[ -n "$COUNT" ] || fail "$PROFILE: métrica $NAME ausente"

if [ -n "$EQUALS" ]; then
    awk -v c="$COUNT" -v e="$EQUALS" 'BEGIN { exit (c + 0 == e + 0) ? 0 : 1 }' || fail "$PROFILE: $NAME = $COUNT, esperado $EQUALS"
fi

if [ -n "$AT_LEAST" ]; then
    awk -v c="$COUNT" -v e="$AT_LEAST" 'BEGIN { exit (c + 0 >= e + 0) ? 0 : 1 }' || fail "$PROFILE: $NAME = $COUNT, esperado ao menos $AT_LEAST"
fi

VALUE=$(awk -F '\t' -v name="$NAME" '$1 == "metric" && $2 == name { print $4 }' "$FILE")

if [ -n "$VALUE_AT_LEAST" ]; then
    awk -v v="$VALUE" -v e="$VALUE_AT_LEAST" 'BEGIN { exit (v + 0 >= e + 0) ? 0 : 1 }' || fail "$PROFILE: valor de $NAME = $VALUE, esperado ao menos $VALUE_AT_LEAST"
    ok "$PROFILE: valor de $NAME = $VALUE, ao menos $VALUE_AT_LEAST"
fi

if [ -n "$VALUE_AT_MOST" ]; then
    awk -v v="$VALUE" -v e="$VALUE_AT_MOST" 'BEGIN { exit (v + 0 <= e + 0) ? 0 : 1 }' || fail "$PROFILE: valor de $NAME = $VALUE, esperado no máximo $VALUE_AT_MOST"
    ok "$PROFILE: valor de $NAME = $VALUE, no máximo $VALUE_AT_MOST"
fi

ok "$PROFILE: $NAME = $COUNT"
