# Shared helpers for the Module Isolation Pilot proofs. Source it; every proof exits 0 only when its claim holds.
set -uo pipefail
ROOT=$(git -C "$(dirname "${BASH_SOURCE[0]}")" rev-parse --show-toplevel)
FEAT=$ROOT/.specs/features/module-isolation-pilot
EVID=$FEAT/evidence
BASE=be1c84c
BUSINESS="backoffice catalog customers fulfillment identity inventory ordering payment"
mkdir -p "$EVID"

start() { OUT="$EVID/$1.txt"; : > "$OUT"; }
say()   { echo "$*" | tee -a "$OUT"; }
fail()  { echo "FAIL: $*" | tee -a "$OUT"; exit 1; }
strip_ansi() { sed -E $'s/\x1B\\[[0-9;]*[A-Za-z]//g'; }

# A disposable worktree of HEAD at .claude/worktrees/removal-<name> without the given modules: their folders
# and their require go, and `composer remove` refreshes the install state and the package discovery. The
# vendor of the main checkout is copied first (its module symlinks are relative). Sets COPY to the path.
removal_copy() {
    local name=$1; shift
    COPY=$ROOT/.claude/worktrees/removal-$name
    git -C "$ROOT" worktree remove --force "$COPY" >/dev/null 2>&1
    rm -rf "$COPY"
    git -C "$ROOT" worktree add --detach "$COPY" HEAD >>"$OUT" 2>&1 || fail "git worktree add $COPY"
    cp -a "$ROOT/backend/vendor" "$COPY/backend/vendor"
    cp "$ROOT/backend/.env" "$COPY/backend/.env"
    local packages=()
    for module in "$@"; do
        rm -rf "$COPY/backend/app-modules/$module"
        packages+=("modules/$module")
    done
    say "removal copy $name without: $*"
    (cd "$COPY" && docker compose run --rm --no-deps -T api composer remove "${packages[@]}" --no-interaction) >>"$OUT" 2>&1 \
        || fail "composer remove ${packages[*]}"
    (cd "$COPY" && make test-prepare) >>"$OUT" 2>&1 || fail "make test-prepare in $COPY"
    COPY_DB=ecommerce_testing_removal_$(printf '%s' "$name" | tr 'A-Z-' 'a-z_')
}

# php inside the removal copy, against the copy's own test database and RabbitMQ vhost.
in_copy() {
    (cd "$COPY" && docker compose run --rm --no-deps -T -e DB_DATABASE="$COPY_DB" -e RABBITMQ_VHOST="$COPY_DB" api "$@")
}

# Removes the removal copy and its test database; the proof's evidence stays.
drop_copy() {
    (cd "$COPY" && make test-db-drop) >/dev/null 2>&1
    git -C "$ROOT" worktree remove --force "$COPY" >/dev/null 2>&1
    rm -rf "$COPY"
}
