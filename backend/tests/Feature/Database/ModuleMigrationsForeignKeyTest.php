<?php

use Illuminate\Support\Facades\DB;

/*
 * The migrations of different modules run in one order (by file name), so a table created by one module
 * must exist before another module's migration points a foreign key at it.
 */
it('creates the cross module foreign keys', function (string $column, string $references) {
    [$table, $name] = explode('.', $column);

    $row = DB::selectOne(
        'select ccu.table_name as referenced from information_schema.table_constraints tc
         join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name and kcu.table_schema = tc.table_schema
         join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
         where tc.constraint_type = \'FOREIGN KEY\' and tc.table_schema = current_schema() and tc.table_name = ? and kcu.column_name = ?',
        [$table, $name],
    );

    expect($row?->referenced)->toBe($references);
})->with([
    'orders.customer_id' => ['orders.customer_id', 'customers'],
    'order_items.product_id' => ['order_items.product_id', 'products'],
    'stocks.product_id' => ['stocks.product_id', 'products'],
    'payments.order_id' => ['payments.order_id', 'orders'],
    'customer_addresses.customer_id' => ['customer_addresses.customer_id', 'customers'],
]);
