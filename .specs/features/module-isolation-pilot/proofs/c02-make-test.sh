#!/usr/bin/env bash
# C2: one make test runs, in the same run and with exit 0, a module test (Payment, in app-modules) and a composition test (backend/tests).
source "$(dirname "$0")/lib.sh"; start c02
(cd "$ROOT" && make test) 2>&1 | strip_ansi > "$EVID/c02-make-test.txt"; code=${PIPESTATUS[0]}
say "make test exit $code: $(grep -E '^\s*Tests:' "$EVID/c02-make-test.txt" | tail -1)"
[ "$code" -eq 0 ] || fail "make test exited $code"
grep -qE 'PASS +Appmodules\\payment\\tests\\Unit\\Gateways\\FakePaymentGatewayTest' "$EVID/c02-make-test.txt" || fail "the Payment module test file did not run"
grep -qE '✓ it approves the fake approved card' "$EVID/c02-make-test.txt" || fail "the case 'approves the fake approved card' did not run"
grep -qE 'PASS +Tests\\Feature\\DeliveryEstimateTest' "$EVID/c02-make-test.txt" || fail "the composition test file did not run"
grep -qE '✓ it shows the delivery estimate after the payment is' "$EVID/c02-make-test.txt" || fail "the composition case did not run"
say "PASS: one make test ran the Payment module test and the composition test"
