#!/usr/bin/env bash
# C29: in a copy without the payment module, migrate:fresh passes, POST /api/orders/{order}/payment answers 404,
# and the tests folder of every remaining module that has one passes.
source "$(dirname "$0")/lib.sh"; start c29
removal_copy without-payment payment
cp "$FEAT/proofs/c29-WithoutPaymentTest.php" "$COPY/backend/app-modules/shared/tests/Feature/WithoutPaymentProofTest.php"
in_copy php artisan migrate:fresh --force >>"$OUT" 2>&1 || fail "migrate:fresh"
say "migrate:fresh: ok"
folders=$(cd "$COPY/backend" && ls -d app-modules/*/tests | tr '\n' ' ')
say "tests folders left: $folders"
in_copy sh -c "php artisan config:clear --quiet && exec ./vendor/bin/pest $folders" 2>&1 | strip_ansi >>"$OUT"
status=${PIPESTATUS[0]}
summary=$(grep -E '^\s*Tests:' "$OUT" | tail -1)
say "pest $folders: $summary (exit $status)"
[ "$status" -eq 0 ] || fail "the remaining modules do not pass without the payment module"
grep -qE 'PASS +Appmodules\\shared\\tests\\Feature\\WithoutPaymentProofTest' "$OUT" || fail "the 404 proof did not run"
echo "$summary" | grep -qE 'skipped|incomplete|risky|todo' && fail "a test was skipped"
drop_copy
say "PASS: without the payment module the others migrate and pass, and the route is gone"
