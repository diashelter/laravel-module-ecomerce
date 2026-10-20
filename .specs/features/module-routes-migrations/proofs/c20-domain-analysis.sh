#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c20
f="$BUILD/docs/domain-analysis.md"
today=$(date +%d/%m/%Y)
head -6 "$f" | grep -q "Última atualização: $today" || fail "the 'Última atualização' line does not carry $today"
for step in "Module Routes" "Module Migrations" "Load Baseline"; do
  grep -E "$step" "$f" | grep -q '✅\|Concluído\|concluíd' || fail "no completed mention of $step"
done
awk '/^## Plano de evolução/{f=1} f' "$f" | grep -E 'HEL-12' | grep -q "Concluído em $today" || fail "no HEL-12 step of the plan concluded on $today"
grep -q "Rotas e migrations por módulo, e não centralizadas" "$f" || fail "the 'Rotas centralizadas' decision was not replaced"
grep -q "Rotas centralizadas em" "$f" && fail "the old 'Rotas centralizadas' decision is still there"
say "OK"
