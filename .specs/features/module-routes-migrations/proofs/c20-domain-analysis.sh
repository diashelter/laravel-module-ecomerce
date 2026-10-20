#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c20
f="$BUILD/docs/domain-analysis.md"
head -6 "$f" | grep -qE "Última atualização: [0-3][0-9]/10/2026" || fail "the 'Última atualização' line carries no October 2026 date"
steps=$(awk '/^## Plano de evolução/{f=1} f' "$f")
# the three steps of the evolution plan, each marked done (✅) with its own "Concluído em dd/mm/yyyy" date
for step in "Módulos como pacotes" "Rotas e migrations por módulo" "Load Baseline"; do
  line=$(grep -E "✅ \*\*$step" <<<"$steps" | head -1)
  [ -n "$line" ] || fail "no ✅ plan step named '$step'"
  grep -qE 'Concluído em [0-3][0-9]/[01][0-9]/20[0-9]{2}' <<<"$line" || fail "the plan step '$step' carries no 'Concluído em dd/mm/yyyy' date"
  say "step '$step' done with a date"
done
grep -q "Rotas e migrations por módulo, e não centralizadas" "$f" || fail "the 'Rotas centralizadas' decision was not replaced"
grep -q "Rotas centralizadas em" "$f" && fail "the old 'Rotas centralizadas' decision is still there"
say "OK"
