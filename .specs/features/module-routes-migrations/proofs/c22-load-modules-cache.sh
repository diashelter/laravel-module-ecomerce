#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c22
grep -q 'APP_MODULES_CACHE: /tmp/laravel/app-modules.php' "$BUILD/load-tests/docker-compose.yml" || fail "the load compose does not set APP_MODULES_CACHE"
(cd "$BUILD" && make load-test TYPE=smoke > "$EVID/c22-load-smoke.txt" 2>&1) || { (cd "$BUILD" && make load-down >/dev/null 2>&1); fail "make load-test TYPE=smoke failed"; }
LOADC() { (cd "$BUILD" && CACHE_LIMITER= docker compose -f load-tests/docker-compose.yml "$@"); }
rc=0
for service in api queue-worker outbox-relay event-consumer; do
  value=$(LOADC exec -T "$service" printenv APP_MODULES_CACHE 2>/dev/null | tr -d '[:space:]')
  say "$service APP_MODULES_CACHE=$value"
  [ "$value" = "/tmp/laravel/app-modules.php" ] || { say "FAIL: $service has the wrong APP_MODULES_CACHE"; rc=1; }
done
LOADC exec -T api test -s /tmp/laravel/app-modules.php || { say "FAIL: modules:cache wrote no /tmp/laravel/app-modules.php in the api container"; rc=1; }
[ ! -e "$BUILD/backend/bootstrap/cache/app-modules.php" ] || { say "FAIL: bootstrap/cache/app-modules.php exists"; rc=1; }
(cd "$BUILD" && make load-down >/dev/null 2>&1)
[ "$rc" = "0" ] || fail "the module cache is not confined to the load container"
say "OK - 4 services set APP_MODULES_CACHE, the api container holds the cache file, and bootstrap/cache has none"
