#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c20
f="$BUILD/docs/domain-analysis.md"
head -6 "$f" | grep -qE "Última atualização: 2[0-9]/10/2026" || fail "the 'Última atualização' line does not carry a date of this work (19 or 20/10/2026 or later in October)"
for step in "Module Routes" "Module Migrations" "Load Baseline"; do
  grep -E "$step" "$f" | grep -q '✅\|Concluído\|concluíd' || fail "no completed mention of $step"
done
awk '/^## Plano de evolução/{f=1} f' "$f" | grep -E 'HEL-12' | grep -qE "Concluído em 2[0-9]/10/2026" || fail "no HEL-12 step of the plan concluded in October 2026"
grep -q "Rotas e migrations por módulo, e não centralizadas" "$f" || fail "the 'Rotas centralizadas' decision was not replaced"
grep -q "Rotas centralizadas em" "$f" && fail "the old 'Rotas centralizadas' decision is still there"
say "OK"
