#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c04
[ -z "$(git -C "$BUILD" status --porcelain)" ] || fail "the build worktree must be clean (commit first) so the diff is attributable"
restore() { git -C "$BUILD" checkout -q -- backend/composer.json backend/composer.lock; rm -rf "$BUILD/backend/app-modules/sample"; (cd "$BUILD" && docker compose run --rm --no-deps -T api composer install --no-interaction --no-progress >/dev/null 2>&1); }
trap restore EXIT
(cd "$BUILD" && printf 'no\nyes\n' | docker compose run --rm --no-deps -T api php artisan make:module sample >"$EVID/c04-make-module.txt" 2>&1) || fail "make:module failed"
(cd "$BUILD" && docker compose run --rm --no-deps -T api composer update modules/sample --no-interaction --no-progress >>"$EVID/c04-make-module.txt" 2>&1) || fail "composer update modules/sample failed"
changed=$(git -C "$BUILD" status --porcelain --untracked-files=all | awk '{print $2}' | grep -v '^backend/app-modules/sample/' | sort)
say "changed outside app-modules/sample:"; say "$changed"
[ "$changed" = $'backend/composer.json\nbackend/composer.lock' ] || fail "expected exactly backend/composer.json and backend/composer.lock"
say "OK"
