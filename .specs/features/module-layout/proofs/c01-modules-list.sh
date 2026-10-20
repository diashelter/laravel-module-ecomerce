#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c01
list=$(art modules:list | strip_ansi); say "$list"
expected="backoffice catalog customers fulfillment identity inventory messaging ordering payment shared"
actual=$(awk -F'|' 'NR>3 && $2 ~ /[a-z]/ {gsub(/ /,"",$2); print $2}' <<<"$list" | sort | tr '\n' ' ' | sed 's/ $//')
say "modules: $actual"
[ "$actual" = "$expected" ] || fail "expected [$expected]"
for m in $expected; do
  studly="$(tr '[:lower:]' '[:upper:]' <<<"${m:0:1}")${m:1}"
  grep -E "^\| $m +\|" <<<"$list" | grep -q "Modules\\\\$studly" || fail "module $m does not declare the namespace Modules\\$studly"
done
say "OK"
