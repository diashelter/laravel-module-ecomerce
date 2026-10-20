#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c14
clone=${SPIKE_CLONE_DIR:-${TMPDIR:-/tmp}/spike-clone}
rm -rf "$clone"; git clone -q --branch spike/hel-12-library "$ROOT" "$clone" || fail "clone failed"
[ ! -d "$clone/backend/vendor" ] || fail "the clone already has vendor/"
cp "$ROOT/backend/.env" "$clone/backend/.env"
(cd "$clone" && docker compose run --rm --no-deps -T api composer install --no-interaction --no-progress >"$EVID/c14-composer.txt" 2>&1) || { tail -5 "$EVID/c14-composer.txt"; fail "composer install failed in the clone"; }
link="$clone/backend/vendor/modules/payment"
[ -L "$link" ] || fail "vendor/modules/payment is not a symlink"
target=$(readlink "$link"); say "vendor/modules/payment -> $target"
case "$target" in *app-modules/payment*) ;; *) fail "symlink does not point at app-modules/payment";; esac
say "OK"
