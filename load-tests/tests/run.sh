#!/bin/sh
# Runs one profile: sh load-tests/tests/run.sh --only <profile>. Exits with the code of make load-test.
. "$(dirname "$0")/common.sh"

[ -n "$CASE" ] || fail "informe o perfil: --only <perfil>"
make load-test TYPE="$CASE"
