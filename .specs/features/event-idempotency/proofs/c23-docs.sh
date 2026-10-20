#!/usr/bin/env bash
# C23: the docs describe the deliveries table and the Fulfillment guard, and no longer say that the
# DeliverOrder retry announces the delivery again. Exits 0 only when every claim holds; the output
# is also written to evidence/c23.txt.
set -u

root="$(cd "$(dirname "$0")/../../../.." && pwd)"
evidence="$root/.specs/features/event-idempotency/evidence/c23.txt"
mkdir -p "$(dirname "$evidence")"

readme="$root/README.md"
agents="$root/AGENTS.md"
analysis="$root/docs/domain-analysis.md"

run() {
    local failures=0

    check() {
        local description="$1"
        shift

        if "$@"; then
            echo "ok    $description"
        else
            echo "FAIL  $description"
            failures=$((failures + 1))
        fi
    }

    absent() { ! grep -q "$@"; }

    check "README describes the deliveries table" grep -q '^| `deliveries` |' "$readme"
    check "README describes the Fulfillment guard (delivered_at makes the retry a no-op)" grep -q 'já preenchido o torna um no-op' "$readme"
    check "README does not say the DeliverOrder retry announces the delivery again" absent 'anuncia a entrega de novo) é ignorado' "$readme"
    check "README names the redelivery test per consumer" grep -q 'ConsumerRedeliveryTest' "$readme"
    check "README lists the 15 frozen migration names" grep -q 'congela os 15 nomes' "$readme"
    check "AGENTS.md states the redelivery test rule and the producer pattern" grep -q 'ConsumerRedeliveryTest' "$agents"
    check "AGENTS.md freezes 15 migration names" grep -q 'congela os 15 nomes' "$agents"
    check "domain analysis cites the deliveries table" grep -q 'tabela `deliveries`' "$analysis"
    check "domain analysis marks problem 18 as solved" grep -q '^#### 18. ✅ Resolvido' "$analysis"

    echo "c23-docs: $failures failure(s)"

    return $((failures > 0 ? 1 : 0))
}

run | tee "$evidence"

exit "${PIPESTATUS[0]}"
