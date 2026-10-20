#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c10
make -C "$BUILD" test-prepare >/dev/null 2>&1 || fail "could not prepare the test database"
art migrate:fresh --force > "$EVID/c10-fresh.txt" || fail "migrate:fresh exited non-zero"
expected=$(git -C "$ROOT" ls-tree --name-only "$BASE:backend/database/migrations" | sed 's/\.php$//')
actual=$(art migrate:status | strip_ansi | awk '/Ran/{print $1}')
say "$actual"
[ "$(wc -l <<<"$actual" | tr -d ' ')" = "14" ] || fail "expected 14 Ran after migrate:fresh"
[ "$actual" = "$expected" ] || { diff <(echo "$expected") <(echo "$actual") | tee -a "$OUT"; fail "the order of the Ran migrations differs from the base"; }
say "OK - migrate:fresh exit 0, 14 Ran in the order of the base"
