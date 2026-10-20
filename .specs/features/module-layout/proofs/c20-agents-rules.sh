#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c20
f="$BUILD/AGENTS.md"
for needle in 'app-modules' 'Contracts' 'Events' 'Core' 'Persistence' 'segunda implementação' 'duplo de teste'; do
  grep -q "$needle" "$f" || fail "AGENTS.md lacks '$needle'"
done
grep -qE 'Http[^A-Za-z]{1,8}Core[^A-Za-z]{1,8}Persistence' "$f" || fail "AGENTS.md does not state the direction Http -> Core -> Persistence"
grep -qiE 'interface' "$f" || fail "AGENTS.md has no interface criterion"
say "OK"
