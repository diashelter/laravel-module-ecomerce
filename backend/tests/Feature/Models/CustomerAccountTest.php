<?php

use App\Modules\Ordering\Models\Order;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Postgres aborts the test transaction after a failed statement, so each refused write runs
// in a nested transaction (a savepoint).
function refused(Closure $write): bool
{
    try {
        DB::transaction($write);

        return false;
    } catch (QueryException) {
        return true;
    }
}

it('ties orders to existing customers and keeps customers that have orders', function () {
    $buyer = customer();
    $free = customer();
    Order::factory()->for($buyer, 'customer')->create();

    expect(refused(fn () => DB::table('customers')->where('id', $buyer->id)->delete()))->toBeTrue()
        ->and(DB::table('customers')->where('id', $buyer->id)->exists())->toBeTrue()
        ->and(DB::table('customers')->where('id', $free->id)->delete())->toBe(1)
        ->and(refused(fn () => DB::table('orders')->insert(orderRow(999999))))->toBeTrue();
});

it('rejects invalid customer e-mails in the database', function (string $email) {
    $insert = fn (string $value) => DB::table('customers')->insert(['name' => 'Ana', 'email' => $value, 'password' => 'x']);

    // The same e-mail in users does not stand in the way of the first customer row.
    admin(['email' => 'ana@example.com']);
    expect($insert('ana@example.com'))->toBeTrue()
        ->and(refused(fn () => $insert($email)))->toBeTrue()
        ->and(DB::table('customers')->count())->toBe(1);
})->with([
    'upper case' => ['Ana@example.com'],
    'padded' => [' ana@example.com'],
    'duplicate' => ['ana@example.com'],
]);
