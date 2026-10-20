#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c17
section=$(awk '/^### Library Spike/{f=1; next} /^### /{f=0} f' "$DESIGN")
if grep -q 'Veredito: bloqueia\.' <<<"$section"; then
  for slice in 'Module Layout' 'Module Routes' 'Module Migrations'; do
    body=$(awk -v s="### $slice" '$0==s{f=1; next} /^### /{f=0} f' "$DESIGN")
    grep -qi 'alternativa leve' <<<"$body" || fail "an answer blocks, but '$slice' is not marked as switched to the light alternative"
  done
  say "OK - a blocking answer exists and the three slices are marked"
else
  grep -q 'Nenhuma resposta bloqueia' <<<"$section" || fail "no answer blocks, but the section does not say 'Nenhuma resposta bloqueia'"
  say "OK - no answer blocks, and the section says so"
fi
