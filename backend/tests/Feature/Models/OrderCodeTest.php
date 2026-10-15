<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// The nested transaction is a savepoint: Postgres aborts the test transaction after a failed statement.
function writeOrderWithCode(?string $code): bool
{
    return DB::transaction(fn () => DB::table('orders')->insert(orderRow(customer()->id, ['code' => $code])));
}

it('guards the order code format in the orders table', function (?string $code, bool $accepted) {
    if ($accepted) {
        expect(writeOrderWithCode($code))->toBeTrue()
            ->and(DB::table('orders')->where('code', $code)->count())->toBe(1);

        return;
    }

    expect(fn () => writeOrderWithCode($code))->toThrow(QueryException::class);
    expect(DB::table('orders')->count())->toBe(0);
})->with([
    'null' => [null, false],
    'seven characters' => ['K7Q2M9X', false],
    'nine characters' => ['K7Q2M9XAB', false],
    'a zero' => ['K7Q2M9X0', false],
    'a letter O' => ['K7Q2M9XO', false],
    'a one' => ['K7Q2M9X1', false],
    'a letter I' => ['K7Q2M9XI', false],
    'a letter L' => ['K7Q2M9XL', false],
    'a lower case letter' => ['k7Q2M9XA', false],
    'a valid code' => ['K7Q2M9XA', true],
]);

it('guards the order code format in the orders table: names the format constraint when the code is malformed', function (string $code) {
    expect(fn () => writeOrderWithCode($code))->toThrow(QueryException::class, 'orders_code_format');
})->with(['K7Q2M9X', 'K7Q2M9X0', 'K7Q2M9XO', 'K7Q2M9X1', 'K7Q2M9XI', 'K7Q2M9XL', 'k7Q2M9XA']);

it('refuses a repeated order code in the orders table', function () {
    expect(writeOrderWithCode('K7Q2M9XA'))->toBeTrue();

    expect(fn () => writeOrderWithCode('K7Q2M9XA'))->toThrow(QueryException::class, 'orders_code_unique');
    expect(DB::table('orders')->count())->toBe(1);
});
