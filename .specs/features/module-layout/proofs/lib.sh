# Shared helpers for the Module Layout proofs. Source it; every proof exits 0 only when its claim holds.
set -uo pipefail
ROOT=$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --path-format=absolute --git-common-dir | sed "s|/.git$||")
BUILD=$ROOT/.claude/worktrees/hel-12-module-layout
FEAT=$ROOT/.specs/features/module-layout
EVID=$FEAT/evidence
TESTDB=ecommerce_testing_hel_12_module_layout
BASE=f1dd960
mkdir -p "$EVID"

# php artisan inside the build worktree, against the worktree's test database
art() { (cd "$BUILD" && docker compose run --rm --no-deps -T -e DB_DATABASE="$TESTDB" -e RABBITMQ_VHOST="$TESTDB" api php artisan "$@" 2>/dev/null); }
# the same, against the development database (read-only uses only)
art_dev() { (cd "$BUILD" && docker compose run --rm --no-deps -T api php artisan "$@" 2>/dev/null); }

start() { OUT="$EVID/$1.txt"; : > "$OUT"; }
say()   { echo "$*" | tee -a "$OUT"; }
fail()  { echo "FAIL: $*" | tee -a "$OUT"; exit 1; }
strip_ansi() { sed -E $'s/\x1B\\[[0-9;]*[A-Za-z]//g'; }
