#!/usr/bin/env bash
# C5: make test-filter with the name of a module test runs exactly that one test, from app-modules/payment/tests/Unit.
source "$(dirname "$0")/lib.sh"; start c05
(cd "$ROOT" && make test-filter FILTER="approves the fake approved card") 2>&1 | strip_ansi > "$EVID/c05-test-filter.txt"; code=${PIPESTATUS[0]}
summary=$(grep -E '^\s*Tests:' "$EVID/c05-test-filter.txt" | tail -1)
say "exit $code: $summary"
[ "$code" -eq 0 ] || fail "make test-filter exited $code"
echo "$summary" | grep -qE 'Tests: +1 passed' || fail "expected exactly 1 test: $summary"
grep -qE 'PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest' "$EVID/c05-test-filter.txt" || fail "the test did not come from the Payment module"
say "PASS: the filter ran the one Payment module test"
