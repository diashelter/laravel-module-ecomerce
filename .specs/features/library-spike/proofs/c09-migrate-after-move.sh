#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c09
mod="$SPIKE/backend/app-modules/payment/database/migrations/2026_10_06_000001_create_payments_table.php"
old="$SPIKE/backend/database/migrations/2026_10_06_000001_create_payments_table.php"
tmp="$EVID/payments-migration.moved.bak"
[ -f "$mod" ] || fail "the migration is not in the module"
mv "$mod" "$tmp"; cp "$tmp" "$old"
trap 'rm -f "$old"; [ -f "$tmp" ] && mv "$tmp" "$mod"' EXIT
art migrate:fresh --force >/dev/null || fail "migrate:fresh with the migration in the old place failed"
# now the file lives only in the module
rm -f "$old"; mv "$tmp" "$mod"
res=$(art migrate)
say "$res"
grep -q 'Nothing to migrate' <<<"$res" || fail "expected 'Nothing to migrate.'"
say "OK"
