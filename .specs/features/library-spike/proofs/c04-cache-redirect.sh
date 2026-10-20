#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c04
src="$SPIKE/backend/vendor/internachi/modular/src/Support/ModularServiceProvider.php"
line=$(grep -n "bootstrapPath('cache/app-modules.php')" "$src") || fail "cache path expression not found in the library"
say "library source: $line"
art modules:clear >/dev/null
# Try the redirect the framework offers its own caches (APP_*_CACHE), by analogy; run in one container to see /tmp.
res=$(cd "$SPIKE" && docker compose run --rm --no-deps -T -e DB_DATABASE="$TESTDB" -e APP_MODULES_CACHE=/tmp/laravel/app-modules.php api sh -c \
  'mkdir -p /tmp/laravel && php artisan modules:cache >/dev/null 2>&1; ls -l /tmp/laravel/app-modules.php 2>&1; ls -l bootstrap/cache/app-modules.php 2>&1; rm -f bootstrap/cache/app-modules.php' 2>/dev/null)
say "$res"
if grep -q '^-.*/tmp/laravel/app-modules.php' <<<"$res"; then
  say "ANSWER: yes APP_MODULES_CACHE"
else
  say "ANSWER: no - the path is bootstrapPath('cache/app-modules.php') with no environment variable; only modules:cache and optimize write it, and it holds module paths, not configuration"
fi
art modules:clear >/dev/null
[ ! -e "$SPIKE/backend/bootstrap/cache/app-modules.php" ] || fail "left a cache file behind"
say "OK"
