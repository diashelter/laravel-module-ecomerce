#!/usr/bin/env bash
# C6: in a copy where every business module but <module> was removed (all eight for shared and messaging),
# migrate:fresh passes and the whole tests folder of <module> passes, with no test skipped.
source "$(dirname "$0")/lib.sh"
MODULE=${1:?usage: c06-module-alone.sh <module>}
start "c06-$MODULE"

REMOVE=()
for module in $BUSINESS; do
    [ "$module" = "$MODULE" ] || REMOVE+=("$module")
done
removal_copy "$MODULE-alone" "${REMOVE[@]}"
say "packages left: $(ls "$COPY/backend/app-modules" | tr '\n' ' ')"

in_copy php artisan migrate:fresh --force >>"$OUT" 2>&1 || fail "migrate:fresh"
say "migrate:fresh: ok"

in_copy sh -c "php artisan config:clear --quiet && exec ./vendor/bin/pest app-modules/$MODULE/tests" 2>&1 | strip_ansi >>"$OUT"
status=${PIPESTATUS[0]}
summary=$(grep -E "^\s*Tests:" "$OUT" | tail -1)
say "pest app-modules/$MODULE/tests: $summary (exit $status)"
[ "$status" -eq 0 ] || fail "the tests of $MODULE do not pass alone"
echo "$summary" | grep -qE "[0-9]+ passed" || fail "no test ran"
echo "$summary" | grep -qE "skipped|incomplete|risky|todo" && fail "a test was skipped"

drop_copy
say "PASS: the tests of $MODULE pass with only $MODULE, shared, messaging and integration installed"
