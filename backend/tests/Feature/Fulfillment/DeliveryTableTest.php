<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fulfillment\Persistence\Models\Delivery;

it('refuses a second delivery for the order and the deletion of an order with a delivery', function () {
    $delivery = Delivery::factory()->create();

    // Each statement runs in a savepoint: PostgreSQL aborts the whole test transaction on a violation.
    $duplicate = fn () => DB::transaction(fn () => Delivery::factory()->create(['order_id' => $delivery->order_id]));
    $deletion = fn () => DB::transaction(fn () => DB::table('orders')->where('id', $delivery->order_id)->delete());

    // 23505 is unique_violation; ON DELETE RESTRICT answers 23001 (restrict_violation), not 23503.
    expect($duplicate)->toThrow(fn (QueryException $e) => expect($e->getCode())->toBe('23505'))
        ->and($deletion)->toThrow(function (QueryException $e) {
            expect($e->getCode())->toBe('23001')
                ->and($e->getMessage())->toContain('deliveries_order_id_foreign');
        });

    expect(Delivery::query()->count())->toBe(1)
        ->and(DB::table('orders')->where('id', $delivery->order_id)->exists())->toBeTrue();
});

it('declares the delivered state as a nullable delivered_at', function () {
    $columns = collect(Schema::getColumns('deliveries'))->keyBy('name');

    expect($columns['id']['type_name'])->toBe('uuid')
        ->and($columns['order_id']['type_name'])->toBe('uuid')
        ->and($columns['estimated_delivery_on']['nullable'])->toBeFalse()
        ->and($columns['delivered_at']['nullable'])->toBeTrue();
});
