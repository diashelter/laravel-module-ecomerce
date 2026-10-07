<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('guards the delivery copy and the shipping in the orders table', function (array $override, bool $accepted) {
    $user = customer();
    // The nested transaction is a savepoint: Postgres aborts the test transaction after a failed statement.
    $write = fn () => DB::transaction(fn () => DB::table('orders')->insert(orderRow($user->id, $override)));

    if ($accepted) {
        $write();
        expect(DB::table('orders')->where('customer_id', $user->id)->count())->toBe(1);

        return;
    }

    expect($write)->toThrow(QueryException::class);
    expect(DB::table('orders')->where('customer_id', $user->id)->count())->toBe(0);
})->with(function () {
    foreach (['recipient_name', 'postal_code', 'street', 'number', 'district', 'city', 'state'] as $column) {
        yield "{$column} null" => [["delivery_{$column}" => null], false];
    }

    yield 'business days null' => [['delivery_business_days' => null], false];
    yield 'negative shipping' => [['shipping_cents' => -1], false];
    yield 'zero business days' => [['delivery_business_days' => 0], false];
    yield 'total below the shipping' => [['total_cents' => 100, 'shipping_cents' => 200], false];
    yield 'unknown state' => [['delivery_state' => 'XX'], false];
    yield 'a letter in the postal code' => [['delivery_postal_code' => '0131010A'], false];
    yield 'no complement and no estimate' => [['delivery_complement' => null, 'estimated_delivery_on' => null], true];
});
