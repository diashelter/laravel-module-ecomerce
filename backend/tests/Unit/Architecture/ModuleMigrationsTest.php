<?php

/*
 * Each module owns the migrations of its tables, in app-modules/<module>/database/migrations. The migrations
 * table stores the file name, so a name that changes would run the SQL again on a migrated database: the
 * fifteen names are frozen here.
 */

const FRAMEWORK_MIGRATIONS = [
    '0001_01_01_000001_create_cache_table',
    '0001_01_01_000002_create_jobs_table',
    '2026_10_18_170032_create_features_table',
];

/** migration name => the module that owns its tables (users also carries password_reset_tokens and sessions). */
const MODULE_MIGRATION_OWNERS = [
    '0001_01_01_000000_create_users_table' => 'identity',
    '2026_10_05_000000_create_customers_table' => 'identity',
    '2026_10_05_000001_create_categories_table' => 'catalog',
    '2026_10_05_000002_create_products_table' => 'catalog',
    '2026_10_05_000003_create_stocks_table' => 'inventory',
    '2026_10_05_000004_create_category_product_table' => 'catalog',
    '2026_10_05_000005_create_orders_table' => 'ordering',
    '2026_10_05_000006_create_order_items_table' => 'ordering',
    '2026_10_06_000001_create_payments_table' => 'payment',
    '2026_10_07_000001_create_customer_addresses_table' => 'customers',
    '2026_10_15_000001_create_outbox_messages_table' => 'messaging',
    '2026_10_20_000001_create_deliveries_table' => 'fulfillment',
];

/** @return list<string> the migration names the migrator finds in the given paths, sorted as it runs them */
function migrationNamesIn(array $paths): array
{
    return array_keys(app('migrator')->getMigrationFiles($paths));
}

it('keeps only the framework migrations at the root', function () {
    $names = array_map(fn (string $file) => basename($file, '.php'), glob(base_path('database/migrations/*.php')) ?: []);
    sort($names);

    expect($names)->toBe(FRAMEWORK_MIGRATIONS);
});

it('places each module migration in its owner package', function (string $name, string $owner) {
    expect(is_file(base_path("app-modules/{$owner}/database/migrations/{$name}.php")))->toBeTrue()
        ->and(is_file(base_path("database/migrations/{$name}.php")))->toBeFalse();
})->with(array_map(fn (string $name, string $owner) => [$name, $owner], array_keys(MODULE_MIGRATION_OWNERS), MODULE_MIGRATION_OWNERS));

it('keeps the fifteen frozen migration names', function () {
    $paths = [...app('migrator')->paths(), database_path('migrations')];
    $frozen = [...FRAMEWORK_MIGRATIONS, ...array_keys(MODULE_MIGRATION_OWNERS)];
    sort($frozen);

    expect(migrationNamesIn($paths))->toBe($frozen);
});

it('detects a renamed migration', function () {
    $directory = sys_get_temp_dir().'/migration-names-'.uniqid();
    mkdir($directory);
    $frozen = [...FRAMEWORK_MIGRATIONS, ...array_keys(MODULE_MIGRATION_OWNERS)];
    sort($frozen);

    try {
        foreach ($frozen as $name) {
            touch($directory.'/'.($name === '2026_10_05_000005_create_orders_table' ? '2026_10_05_000009_create_orders_table' : $name).'.php');
        }

        expect(migrationNamesIn([$directory]))->not->toBe($frozen);
    } finally {
        array_map('unlink', glob($directory.'/*.php'));
        rmdir($directory);
    }
});
