#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c16
expected=$(git -C "$ROOT" ls-tree --name-only "$BASE:backend/database/migrations" | sed 's/\.php$//' | sort)
[ "$(wc -l <<<"$expected" | tr -d ' ')" = "14" ] || fail "expected 14 migration names on main"
actual=$(art_dev migrate:status | strip_ansi | awk '/Ran/{print $1}')
say "$actual"
[ "$actual" = "$expected" ] || { diff <(echo "$expected") <(echo "$actual") | tee -a "$OUT"; fail "development migrate:status differs from the 14 names of main"; }
pretend=$(art_dev migrate --pretend | strip_ansi); say "$pretend"
grep -q 'Nothing to migrate' <<<"$pretend" || fail "migrate --pretend did not answer 'Nothing to migrate.'"
say "OK"
