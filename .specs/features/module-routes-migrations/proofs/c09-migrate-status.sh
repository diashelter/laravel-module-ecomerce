#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c09
expected=$(git -C "$ROOT" ls-tree --name-only "$BASE:backend/database/migrations" | sed 's/\.php$//' | sort)
[ "$(wc -l <<<"$expected" | tr -d ' ')" = "14" ] || fail "expected 14 migration names at the base"
status=$(art_dev migrate:status | strip_ansi); say "$status"
grep -q 'Pending' <<<"$status" && fail "a migration is Pending in the development database"
actual=$(awk '/Ran/{print $1}' <<<"$status")
[ "$actual" = "$expected" ] || { diff <(echo "$expected") <(echo "$actual") | tee -a "$OUT"; fail "development migrate:status differs from the 14 names of the base"; }
pretend=$(art_dev migrate --pretend | strip_ansi); say "$pretend"
grep -q 'Nothing to migrate' <<<"$pretend" || fail "migrate --pretend did not answer 'Nothing to migrate.'"
say "OK - 14 of 14 Ran, none Pending, Nothing to migrate."
