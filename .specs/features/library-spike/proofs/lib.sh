# Shared helpers for the Library Spike proofs. Source it; every proof exits 0 only when its claim holds.
set -uo pipefail
ROOT=/Users/helterdias/Documents/POC/laravel-module-ecomerce
SPIKE=$ROOT/.claude/worktrees/spike-hel-12-library
FEAT=$ROOT/.specs/features/library-spike
EVID=$FEAT/evidence
TESTDB=ecommerce_testing_spike_hel_12_library
BASE=f1dd960
DESIGN=$ROOT/.design/modular-layout.md
mkdir -p "$EVID"

# php artisan inside the spike worktree, always against the worktree's test database (check C2 audits the log).
art() {
  echo "DB_DATABASE=$TESTDB artisan $*" >> "$EVID/commands.log"
  (cd "$SPIKE" && docker compose run --rm --no-deps -T -e DB_DATABASE="$TESTDB" -e RABBITMQ_VHOST="$TESTDB" api php artisan "$@" 2>/dev/null)
}

start() { OUT="$EVID/$1.txt"; : > "$OUT"; }
say()   { echo "$*" | tee -a "$OUT"; }
fail()  { echo "FAIL: $*" | tee -a "$OUT"; exit 1; }
strip_ansi() { sed -E $'s/\x1B\\[[0-9;]*[A-Za-z]//g'; }
