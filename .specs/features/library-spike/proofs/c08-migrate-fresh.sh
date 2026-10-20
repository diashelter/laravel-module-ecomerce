#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c08
expected=$(git -C "$ROOT" ls-tree --name-only "$BASE:backend/database/migrations" | sed 's/\.php$//' | sort)
n=$(wc -l <<<"$expected" | tr -d ' ')
say "expected $n names from $BASE"
[ "$n" = "14" ] || fail "expected 14 migration names on main, found $n"
art migrate:fresh --force >/dev/null || fail "migrate:fresh did not exit 0"
actual=$(art migrate:status | strip_ansi | awk '/Ran/{print $1}')
say "$actual"
[ "$actual" = "$expected" ] || { diff <(echo "$expected") <(echo "$actual") | tee -a "$OUT"; fail "migrate:status names differ from main, or the order does"; }
[ "$(art migrate:status | strip_ansi | grep -c Ran)" = "$n" ] || fail "not every migration is Ran"
say "OK - $n of $n Ran, same order as main"
