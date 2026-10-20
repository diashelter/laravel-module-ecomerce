#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c10
art migrate:fresh --force >/dev/null || fail "migrate:fresh failed"
sql="select tc.constraint_name||' -> '||ccu.table_name from information_schema.table_constraints tc join information_schema.constraint_column_usage ccu using (constraint_name) where tc.table_name='payments' and tc.constraint_type='FOREIGN KEY'"
res=$(cd "$ROOT" && docker compose exec -T db psql -U "${DB_USERNAME:-ecommerce}" -d "$TESTDB" -tAc "$sql")
say "$res"
grep -q -- '-> orders' <<<"$res" || fail "payments has no foreign key to orders"
say "OK"
