<?php

use App\Modules\Customers\Models\CustomerAddress;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** A complete row for the table, written straight to it with no model in between. */
function addressRow(int $customerId, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $customerId,
        'recipient_name' => 'Ana Souza',
        'postal_code' => '01310100',
        'street' => 'Avenida Paulista',
        'number' => '1000',
        'complement' => null,
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
        'created_at' => now(),
        'updated_at' => now(),
    ], $overrides);
}

it('removes the address book together with the customer account', function () {
    $user = customer();
    CustomerAddress::factory()->count(2)->create(['customer_id' => $user->id]);
    $other = addressOf(customer());

    DB::table('customers')->where('id', $user->id)->delete();

    expect(CustomerAddress::query()->where('customer_id', $user->id)->count())->toBe(0)
        ->and(CustomerAddress::query()->whereKey($other->id)->exists())->toBeTrue();

    // The nested transaction is a savepoint: Postgres aborts the test transaction after a failed statement.
    expect(fn () => DB::transaction(fn () => DB::table('customer_addresses')->insert(addressRow(999999))))
        ->toThrow(QueryException::class);
});

it('rejects invalid postal codes and states in the address book table', function (array $override, bool $accepted) {
    $user = customer();
    $write = fn () => DB::transaction(fn () => DB::table('customer_addresses')->insert(addressRow($user->id, $override)));

    if ($accepted) {
        $write();
        expect(DB::table('customer_addresses')->where('customer_id', $user->id)->count())->toBe(1);

        return;
    }

    expect($write)->toThrow(QueryException::class);
    expect(DB::table('customer_addresses')->where('customer_id', $user->id)->count())->toBe(0);
})->with([
    'a letter in the postal code' => [['postal_code' => '0131010A'], false],
    'seven digits in the postal code' => [['postal_code' => '1234567'], false],
    'unknown state' => [['state' => 'XX'], false],
    'lower case state' => [['state' => 'sp'], false],
    'valid postal code and state' => [['postal_code' => '01310100', 'state' => 'SP'], true],
]);

it('requires every column of the address book table but the complement', function (string $column) {
    $user = customer();
    $write = fn () => DB::transaction(fn () => DB::table('customer_addresses')->insert(addressRow($user->id, [$column => null])));

    expect($write)->toThrow(QueryException::class);
    expect(DB::table('customer_addresses')->count())->toBe(0);
})->with(['customer_id', 'recipient_name', 'postal_code', 'street', 'number', 'district', 'city', 'state']);
