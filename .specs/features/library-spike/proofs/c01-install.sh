#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c01
version=$(cd "$SPIKE" && docker compose run --rm --no-deps -T api composer show internachi/modular 2>/dev/null | awk '/^versions/{print $4}')
say "internachi/modular version: $version"
[[ "$version" == 3.* ]] || fail "expected 3.x, got '$version'"
list=$(art modules:list)
say "$list"
grep -q 'payment' <<<"$list" || fail "modules:list does not list payment"
say "OK"
