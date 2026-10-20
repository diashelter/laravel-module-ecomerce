#!/usr/bin/env bash
# C27: a database migrated with the migrations of the base keeps payments_order_id_foreign; one migrate with the
# migrations of HEAD drops it, keeps the other 5 foreign keys between modules, and leaves every migration as Ran.
source "$(dirname "$0")/lib.sh"; start c27
DB=ecommerce_proof_c27
OLD=$ROOT/.claude/worktrees/proof-c27-base
psql_dev() { (cd "$ROOT" && docker compose exec -T db psql -U ecommerce -d ecommerce -v ON_ERROR_STOP=1 -tAc "$1"); }
psql_proof() { (cd "$ROOT" && docker compose exec -T db psql -U ecommerce -d "$DB" -v ON_ERROR_STOP=1 -tAc "$1"); }
foreign_keys() { psql_proof "select conname from pg_constraint where contype = 'f' order by 1"; }
cleanup() {
    psql_dev "drop database if exists $DB with (force)" >/dev/null 2>&1
    git -C "$ROOT" worktree remove --force "$OLD" >/dev/null 2>&1; rm -rf "$OLD"
}
cleanup
psql_dev "create database $DB owner ecommerce" >>"$OUT" 2>&1 || fail "create database $DB"

# The base, with its own vendor (the vendor of HEAD knows the integration package and the module tests).
git -C "$ROOT" worktree add --detach "$OLD" "$BASE" >>"$OUT" 2>&1 || fail "git worktree add $BASE"
cp -a "$ROOT/backend/vendor" "$OLD/backend/vendor"; cp "$ROOT/backend/.env" "$OLD/backend/.env"
(cd "$OLD" && docker compose run --rm --no-deps -T api composer install --no-interaction) >>"$OUT" 2>&1 || fail "composer install at $BASE"
(cd "$OLD" && docker compose run --rm --no-deps -T -e DB_DATABASE="$DB" api php artisan migrate --force) >>"$OUT" 2>&1 || fail "migrate at $BASE"
before=$(foreign_keys); say "foreign keys at $BASE:"; say "$before"
grep -qx payments_order_id_foreign <<<"$before" || fail "the base did not create payments_order_id_foreign"

(cd "$ROOT" && docker compose run --rm --no-deps -T -e DB_DATABASE="$DB" api php artisan migrate --force) >>"$OUT" 2>&1 || fail "migrate at HEAD"
after=$(foreign_keys); say "foreign keys at HEAD:"; say "$after"
grep -qx payments_order_id_foreign <<<"$after" && fail "payments_order_id_foreign is still there"
for key in orders_customer_id_foreign order_items_product_id_foreign stocks_product_id_foreign customer_addresses_customer_id_foreign deliveries_order_id_foreign; do
    grep -qx "$key" <<<"$after" || fail "the cross module key $key is gone"
done
[ "$(grep -vx payments_order_id_foreign <<<"$before")" = "$after" ] || fail "a foreign key other than payments_order_id_foreign changed"

status=$( (cd "$ROOT" && docker compose run --rm --no-deps -T -e DB_DATABASE="$DB" api php artisan migrate:status) 2>&1 | strip_ansi)
echo "$status" >>"$OUT"
grep -q '2026_10_21_000001_drop_payments_order_id_foreign .* Ran' <<<"$status" || fail "the new migration did not run"
grep -q 'Pending' <<<"$status" && fail "a migration is pending"
cleanup
say "PASS: migrate on a database of the base drops only payments_order_id_foreign, every migration Ran"
