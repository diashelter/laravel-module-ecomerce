#!/usr/bin/env bash
# C4: the Makefile (test, test-filter, test-prepare) and the CI workflow are the ones of the base: no new command, flag or variable.
source "$(dirname "$0")/lib.sh"; start c04
for file in Makefile .github/workflows/ci.yml; do
    git -C "$ROOT" diff --quiet "$BASE" HEAD -- "$file" || fail "$file changed since $BASE"
    say "unchanged since $BASE: $file"
done
say "PASS: no test command, flag or variable changed"
