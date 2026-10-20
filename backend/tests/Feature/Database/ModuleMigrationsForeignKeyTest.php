<?php

use Illuminate\Support\Facades\DB;

/*
 * The migrations of different modules run in one order (by file name), so a table created by one module
 * must exist before another module's migration points a foreign key at it.
 */

/** The table a column's foreign key points at, or null when the column has none. */
function foreignKeyTarget(string $column): ?string
{
    [$table, $name] = explode('.', $column);

    return DB::selectOne(
        'select ccu.table_name as referenced from information_schema.table_constraints tc
         join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name and kcu.table_schema = tc.table_schema
         join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
         where tc.constraint_type = \'FOREIGN KEY\' and tc.table_schema = current_schema() and tc.table_name = ? and kcu.column_name = ?',
        [$table, $name],
    )?->referenced;
}

it('creates the cross module foreign keys', function (string $column, string $references) {
    expect(foreignKeyTarget($column))->toBe($references);
})->with([
    'orders.customer_id' => ['orders.customer_id', 'customers'],
    'order_items.product_id' => ['order_items.product_id', 'products'],
    'stocks.product_id' => ['stocks.product_id', 'products'],
    'customer_addresses.customer_id' => ['customer_addresses.customer_id', 'customers'],
    'deliveries.order_id' => ['deliveries.order_id', 'orders'],
]);

// The payment module runs without the ordering module, so its table never points at orders (see
// .design/module-isolation.md, Key decision 5): the order a payment belongs to is checked by the order port.
it('keeps payments.order_id without a foreign key', function () {
    expect(foreignKeyTarget('payments.order_id'))->toBeNull()
        ->and(DB::table('information_schema.table_constraints')
            ->where('table_schema', DB::raw('current_schema()'))
            ->where('table_name', 'payments')
            ->where('constraint_type', 'FOREIGN KEY')
            ->count())->toBe(0);
});
