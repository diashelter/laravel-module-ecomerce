#!/usr/bin/env bash
source "$(dirname "$0")/lib.sh"; start c08
diff_out=$(git -C "$BUILD" diff -M100% --name-status "$BASE" HEAD -- backend/database backend/app-modules)
declare -A OWNER=(
  [0001_01_01_000000_create_users_table]=identity
  [2026_10_05_000000_create_customers_table]=identity
  [2026_10_05_000001_create_categories_table]=catalog
  [2026_10_05_000002_create_products_table]=catalog
  [2026_10_05_000003_create_stocks_table]=inventory
  [2026_10_05_000004_create_category_product_table]=catalog
  [2026_10_05_000005_create_orders_table]=ordering
  [2026_10_05_000006_create_order_items_table]=ordering
  [2026_10_06_000001_create_payments_table]=payment
  [2026_10_07_000001_create_customer_addresses_table]=customers
  [2026_10_15_000001_create_outbox_messages_table]=messaging
)
for name in "${!OWNER[@]}"; do
  want=$'R100\tbackend/database/migrations/'"$name"$'.php\tbackend/app-modules/'"${OWNER[$name]}"$'/database/migrations/'"$name"$'.php'
  grep -qxF -- "$want" <<<"$diff_out" || fail "no byte-identical rename for $name -> ${OWNER[$name]}"
done
renames=$(grep -c '^R100' <<<"$diff_out"); say "R100 renames: $renames"
[ "$renames" = "11" ] || fail "expected 11 R100 renames, found $renames"
other=$(grep -E 'database/migrations' <<<"$diff_out" | grep -vc '^R100'); say "other changes in migration folders: $other"
[ "$other" = "0" ] || fail "a migration file was added, edited or deleted"
root=$(ls "$BUILD/backend/database/migrations" | sort | tr '\n' ' '); say "root: $root"
[ "$root" = "0001_01_01_000001_create_cache_table.php 0001_01_01_000002_create_jobs_table.php 2026_10_18_170032_create_features_table.php " ] || fail "the root migrations are not cache, jobs and features"
say "OK - 11 migrations moved byte for byte to their owners; 3 framework migrations stay"
