#!/usr/bin/env bash
# C21: in a copy without the ordering module (every business module but payment removed), the container resolves
# the order port to the unavailable implementation and a valid payment answers 503 with nothing charged or written.
source "$(dirname "$0")/lib.sh"; start c21
REMOVE=()
for module in $BUSINESS; do
    [ "$module" = payment ] || REMOVE+=("$module")
done
removal_copy payment-without-ordering "${REMOVE[@]}"
cp "$FEAT/proofs/c21-WithoutOrderingTest.php" "$COPY/backend/app-modules/payment/tests/Feature/WithoutOrderingProofTest.php"
in_copy php artisan migrate:fresh --force >>"$OUT" 2>&1 || fail "migrate:fresh"
in_copy sh -c "php artisan config:clear --quiet && exec ./vendor/bin/pest app-modules/payment/tests/Feature/WithoutOrderingProofTest.php" 2>&1 | strip_ansi >>"$OUT"
status=${PIPESTATUS[0]}
summary=$(grep -E '^\s*Tests:' "$OUT" | tail -1)
say "proof test: $summary (exit $status)"
[ "$status" -eq 0 ] || fail "the payment without the ordering module does not answer 503 as claimed"
echo "$summary" | grep -qE 'Tests: +1 passed' || fail "the proof test did not run"
drop_copy
say "PASS: without the ordering module the payment answers 503 and writes nothing"
