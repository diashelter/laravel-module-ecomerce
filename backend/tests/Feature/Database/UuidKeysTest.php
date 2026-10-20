<?php

use Illuminate\Support\Facades\DB;
use Modules\Catalog\Persistence\Models\Category;
use Modules\Catalog\Persistence\Models\Product;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Fulfillment\Persistence\Models\Delivery;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Identity\Persistence\Models\User;
use Modules\Inventory\Persistence\Models\Stock;
use Modules\Ordering\Persistence\Models\Order;
use Modules\Ordering\Persistence\Models\OrderItem;
use Modules\Payment\Persistence\Models\Payment;

const UUID_KEY_COLUMNS = [
    'users.id', 'customers.id', 'categories.id', 'products.id', 'stocks.id', 'orders.id',
    'order_items.id', 'payments.id', 'customer_addresses.id', 'deliveries.id',
    'stocks.product_id', 'category_product.product_id', 'category_product.category_id',
    'orders.customer_id', 'order_items.order_id', 'order_items.product_id',
    'payments.order_id', 'customer_addresses.customer_id', 'deliveries.order_id',
];

it('types every business key and foreign key as uuid', function (string $column) {
    [$table, $name] = explode('.', $column);

    $row = DB::selectOne(
        'select data_type from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
        [$table, $name],
    );

    expect($row?->data_type)->toBe('uuid');
})->with(UUID_KEY_COLUMNS);

it('assigns a version 7 uuid before the insert', function (string $model, string $table) {
    $record = $model::factory()->create();

    $default = DB::selectOne(
        "select column_default from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = 'id'",
        [$table],
    );

    expect($record->id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($record->fresh()->id)->toBe($record->id)
        ->and($default->column_default)->toBeNull();
})->with([
    [User::class, 'users'],
    [CustomerAccount::class, 'customers'],
    [Category::class, 'categories'],
    [Product::class, 'products'],
    [Stock::class, 'stocks'],
    [Order::class, 'orders'],
    [OrderItem::class, 'order_items'],
    [Payment::class, 'payments'],
    [CustomerAddress::class, 'customer_addresses'],
    [Delivery::class, 'deliveries'],
]);

it('keeps the delete rule of every foreign key', function (string $table, string $column, string $rule) {
    $row = DB::selectOne(
        "select c.confdeltype as rule from pg_constraint c
            join pg_attribute a on a.attrelid = c.conrelid and a.attnum = any(c.conkey)
            where c.contype = 'f' and c.conrelid = ?::regclass and a.attname = ?",
        [$table, $column],
    );

    expect($row?->rule)->toBe($rule);
})->with([
    'orders.customer_id restricts' => ['orders', 'customer_id', 'r'],
    'order_items.product_id restricts' => ['order_items', 'product_id', 'r'],
    'payments.order_id restricts' => ['payments', 'order_id', 'r'],
    'deliveries.order_id restricts' => ['deliveries', 'order_id', 'r'],
    'stocks.product_id cascades' => ['stocks', 'product_id', 'c'],
    'category_product.product_id cascades' => ['category_product', 'product_id', 'c'],
    'category_product.category_id cascades' => ['category_product', 'category_id', 'c'],
    'order_items.order_id cascades' => ['order_items', 'order_id', 'c'],
    'customer_addresses.customer_id cascades' => ['customer_addresses', 'customer_id', 'c'],
]);

it('keeps every constraint of the business tables', function (string $name, string $table) {
    $row = DB::selectOne(
        'select conname from pg_constraint where conname = ? and conrelid = ?::regclass',
        [$name, $table],
    );

    expect($row?->conname)->toBe($name);
})->with([
    ['customer_addresses_postal_code_digits', 'customer_addresses'],
    ['customer_addresses_state_known', 'customer_addresses'],
    ['customers_email_normalized', 'customers'],
    ['order_items_subtotal_cents_non_negative', 'order_items'],
    ['order_items_unit_price_cents_non_negative', 'order_items'],
    ['orders_delivery_business_days_positive', 'orders'],
    ['orders_delivery_postal_code_digits', 'orders'],
    ['orders_delivery_state_known', 'orders'],
    ['orders_shipping_cents_non_negative', 'orders'],
    ['orders_total_cents_non_negative', 'orders'],
    ['orders_total_cents_not_below_shipping', 'orders'],
    ['payments_amount_cents_non_negative', 'payments'],
    ['payments_decline_reason_matches_status', 'payments'],
    ['products_price_cents_non_negative', 'products'],
    ['stocks_quantity_non_negative', 'stocks'],
    ['users_email_normalized', 'users'],
    ['users_role_valid', 'users'],
    ['categories_slug_unique', 'categories'],
    ['customers_email_unique', 'customers'],
    ['stocks_product_id_unique', 'stocks'],
    ['users_email_unique', 'users'],
    ['category_product_pkey', 'category_product'],
]);

it('keeps every constraint of the business tables: the composite key covers both category link columns', function () {
    $columns = DB::select(
        "select a.attname from pg_constraint c join pg_attribute a on a.attrelid = c.conrelid and a.attnum = any(c.conkey)
            where c.conname = 'category_product_pkey' order by a.attname",
    );

    expect(array_column($columns, 'attname'))->toBe(['category_id', 'product_id']);
});

it('keeps every constraint of the business tables: one approved payment per order', function () {
    $index = DB::selectOne("select indexdef from pg_indexes where indexname = 'payments_order_id_approved_unique'");

    expect($index->indexdef)->toContain('CREATE UNIQUE INDEX')
        ->and($index->indexdef)->toContain('(order_id)')
        ->and($index->indexdef)->toContain("status)::text = 'approved'::text");
});
