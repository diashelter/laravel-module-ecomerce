#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c02
before=$(grep -E 'Ran|Pending' "$EVID/dev-migrate-status.before")
after=$(cd "$ROOT" && docker compose run --rm --no-deps -T api php artisan migrate:status 2>/dev/null | grep -E 'Ran|Pending')
[ -n "$before" ] || fail "no 'before' snapshot of the development migrate:status"
[ "$before" = "$after" ] || { diff <(echo "$before") <(echo "$after") | tee -a "$OUT"; fail "development migrate:status changed"; }
say "development migrate:status identical ($(echo "$before" | wc -l | tr -d ' ') rows)"
bad=$(grep -vc "^DB_DATABASE=$TESTDB " "$EVID/commands.log")
say "artisan calls not on the test database: $bad"
[ "$bad" = "0" ] || fail "an artisan call did not use DB_DATABASE=$TESTDB"
say "OK"
