#!/bin/sh
# Runs one case of a k6 test file of load-tests/tests: sh load-tests/tests/k6.sh <file> --only <case>.
FILE="$1"
[ $# -gt 0 ] && shift
. "$(dirname "$0")/common.sh"

[ -n "$CASE" ] || fail "informe o caso: --only <caso>"
$LOAD run --rm --no-deps k6 run -q -e CASE="$CASE" "tests/$FILE"
