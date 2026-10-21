<?php

use Illuminate\Auth\GenericUser;
use Modules\Shared\Support\RecordId;

/*
 * Outside the Identity module the authenticated customer is an id, so a module test signs in with an id
 * alone, never with an account of the Identity module.
 */
it('signs a customer in by id alone', function () {
    $first = signInAsCustomer();

    expect(RecordId::isCanonical($first))->toBeTrue()
        ->and($first[14])->toBe('7')
        ->and(auth('customer')->id())->toBe($first)
        ->and(auth('customer')->user())->toBeInstanceOf(GenericUser::class);

    $second = signInAsCustomer();

    expect($second)->not->toBe($first)
        ->and(auth('customer')->id())->toBe($second);

    $chosen = unknownId();

    expect(signInAsCustomer($chosen))->toBe($chosen)
        ->and(auth('customer')->id())->toBe($chosen);
});
