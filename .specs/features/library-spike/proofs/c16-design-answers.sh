#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c16
section=$(awk '/^### Library Spike/{f=1; next} /^### /{f=0} f' "$DESIGN")
[ -n "$section" ] || fail "section '### Library Spike' not found in the design"
answers=$(grep -cE '^\*\*Resposta [1-5] - ' <<<"$section")
say "answers: $answers"
[ "$answers" = "5" ] || fail "expected exactly 5 answers, found $answers"
for field in 'Comando:' 'Resultado:' 'Efeito:'; do
  n=$(grep -c "^$field" <<<"$section"); say "$field lines: $n"
  [ "$n" = "5" ] || fail "expected 5 '$field' lines, found $n"
done
n=$(grep -cE 'Veredito: (confirma|muda|bloqueia)\.' <<<"$section"); say "Veredito lines: $n"
[ "$n" = "5" ] || fail "every answer needs a 'Veredito: confirma|muda|bloqueia.' (found $n)"
say "OK"
