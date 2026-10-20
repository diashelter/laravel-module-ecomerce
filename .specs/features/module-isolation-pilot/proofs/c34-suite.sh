#!/usr/bin/env bash
# C34: make test exits 0, with no test skipped or incomplete.
source "$(dirname "$0")/lib.sh"; start c34
(cd "$ROOT" && make test) 2>&1 | strip_ansi > "$EVID/c34-make-test.txt"; code=${PIPESTATUS[0]}
summary=$(grep -E '^\s*Tests:' "$EVID/c34-make-test.txt" | tail -1)
say "make test exit $code: $summary"
[ "$code" -eq 0 ] || fail "make test exited $code"
echo "$summary" | grep -qE 'failed|skipped|incomplete|risky|todo' && fail "not a clean run: $summary"
grep -qE '✓ it logs each payment attempt without the card' "$EVID/c34-make-test.txt" || fail "the payment log test did not run"
say "PASS: the whole suite passes with nothing skipped"
