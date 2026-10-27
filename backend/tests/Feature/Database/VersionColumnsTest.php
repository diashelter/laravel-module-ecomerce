<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Door 3 of the storefront read model: the version of a record is a bigint column that is never null and starts at 1.
 */

const VERSION_TABLES = ['products', 'categories', 'stocks'];

/** @return array<string, mixed> the information_schema row of the column */
function versionColumn(string $table): object
{
    return DB::selectOne(
        "select data_type, is_nullable, column_default from information_schema.columns
            where table_schema = current_schema() and table_name = ? and column_name = 'version'",
        [$table],
    );
}

it('adds the version column to products, categories and stocks', function (string $table) {
    $column = versionColumn($table);

    expect($column->data_type)->toBe('bigint')
        ->and($column->is_nullable)->toBe('NO')
        ->and($column->column_default)->toBe("'1'::bigint");
})->with(VERSION_TABLES);

it('gives version 1 to the rows that existed before', function () {
    $migrations = [
        base_path('app-modules/catalog/database/migrations/2026_10_27_000001_add_version_to_products_and_categories_tables.php'),
        base_path('app-modules/inventory/database/migrations/2026_10_27_000002_add_version_to_stocks_table.php'),
    ];
    $loaded = array_map(fn (string $file) => require $file, $migrations);

    // Back to the shape before the migrations, with one row in each table.
    foreach (array_reverse($loaded) as $migration) {
        $migration->down();
    }

    $productId = (string) Str::uuid7();
    DB::table('products')->insert(['id' => $productId, 'name' => 'Antigo', 'price_cents' => 100, 'description' => 'x', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('categories')->insert(['id' => (string) Str::uuid7(), 'name' => 'Antiga', 'slug' => 'antiga', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('stocks')->insert(['id' => (string) Str::uuid7(), 'product_id' => $productId, 'quantity' => 3, 'created_at' => now(), 'updated_at' => now()]);

    foreach ($loaded as $migration) {
        $migration->up();
    }

    expect(DB::table('products')->value('version'))->toBe(1)
        ->and(DB::table('categories')->value('version'))->toBe(1)
        ->and(DB::table('stocks')->value('version'))->toBe(1);
});
