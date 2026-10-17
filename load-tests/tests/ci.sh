#!/bin/sh
# The smoke job of the CI workflow (checks C78 to C81): sh load-tests/tests/ci.sh --only <case>.
. "$(dirname "$0")/common.sh"

WORKFLOW=.github/workflows/ci.yml

# The lines of the load-smoke job, up to the next job or the end of the file.
job() {
    awk '/^  load-smoke:/ { inside = 1; print; next } inside && /^  [a-z][a-z-]*:/ { exit } inside { print }' "$WORKFLOW"
}

# The `run` of the step that follows a given `if:` line in the job.
step_after() {
    job | awk -v condition="$1" 'index($0, condition) { found = 1; next } found && /run:/ { print; exit }'
}

case "$CASE" in
    job)
        [ -n "$(job)" ] || fail "o workflow não tem o job load-smoke"
        grep -qE '^  backend:' "$WORKFLOW" || fail "o job backend sumiu"
        job | grep -q 'make load-test TYPE=smoke' || fail "o job não roda o smoke"
        grep -qE '^  pull_request:' "$WORKFLOW" || fail "o workflow não roda em pull_request"
        grep -qE 'branches: \[main\]' "$WORKFLOW" || fail "o workflow não roda no push da main"
        ok "o job load-smoke roda o smoke em pull_request e no push da main"
        ;;
    summary-in-log)
        LINE=$(job | grep 'make load-test TYPE=smoke')
        echo "$LINE" | grep -qE '[>|]' && fail "a saída do smoke é redirecionada: $LINE"
        ok "o resumo do k6 vai para o log do passo"
        ;;
    failure-logs)
        step_after 'if: failure()' | grep -q 'docker compose -f load-tests/docker-compose.yml logs' || fail "nenhum passo if: failure() com os logs da carga"
        ok "os logs da pilha de carga saem na falha"
        ;;
    always-down)
        step_after 'if: always()' | grep -q 'make load-down' || fail "nenhum passo if: always() com make load-down"
        ok "make load-down roda sempre"
        ;;
    *)
        unknown_case "job summary-in-log failure-logs always-down"
        ;;
esac
