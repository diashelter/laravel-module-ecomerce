#!/bin/sh
# A check of the last run of a profile passed: sh load-tests/tests/assert-check.sh <profile> --name "<check>".
# `--name all-from-smoke-checks` requires every name of tests/smoke-checks.txt.
PROFILE="$1"
NAME=""
shift
while [ $# -gt 0 ]; do
    case "$1" in
        --name)
            [ $# -ge 2 ] || { echo "FALHOU: --name sem valor" >&2; exit 1; }
            NAME="$2"
            shift 2
            ;;
        *) shift ;;
    esac
done
. "$(dirname "$0")/common.sh"

FILE="$RESULTS/$PROFILE-checks.txt"
[ -f "$FILE" ] || fail "$FILE não existe: rode sh load-tests/tests/run.sh --only $PROFILE"

passed() {
    awk -F '\t' -v name="$1" '$1 == "check" && $4 == name && $2 >= 1 && $3 == 0 { found = 1 } END { exit found ? 0 : 1 }' "$FILE"
}

if [ "$NAME" = "all-from-smoke-checks" ]; then
    while IFS= read -r expected; do
        [ -z "$expected" ] && continue
        passed "$expected" || fail "$PROFILE: '$expected' ausente ou com falha"
    done < load-tests/tests/smoke-checks.txt
    ok "$PROFILE: todos os checks de smoke-checks.txt passaram"
    exit 0
fi

passed "$NAME" || fail "$PROFILE: '$NAME' ausente ou com falha ($(grep -F "$NAME" "$FILE" | tr '\t' ' '))"
ok "$PROFILE: $NAME"
