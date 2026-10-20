#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c21
f="$BUILD/docs/domain-analysis.md"
for m in Backoffice Catalog Customers Fulfillment Identity Inventory Messaging Ordering Payment Shared; do
  awk '/^## Estrutura modular adotada/{f=1;next} /^## /{f=0} f' "$f" | grep -qE "^ +$m/" || fail "module $m is not listed in 'Estrutura modular adotada'"
done
grep -q '19/10/2026' "$f" || fail "no 19/10/2026 date"
grep -E 'HEL-12' "$f" | grep -q '✅' || fail "no completed (✅) HEL-12 step"
grep -E 'Messaging' "$f" | grep -qiE 'outbox|mensageria' || fail "Messaging is not described"
grep -qi 'fila do Redis' "$f" || fail "no deploy note about the Redis queue"
head -6 "$f" | grep -q 'Última atualização: 19/10/2026' || fail "the 'Última atualização' line was not updated to 19/10/2026"
say "OK"
