<?php

use App\Modules\Customers\Http\Requests\Admin\StoreCustomerRequest;
use App\Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest;
use App\Modules\Customers\Http\Requests\UpdateProfileRequest;
use App\Modules\Identity\Http\Requests\Admin\StoreStaffMemberRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateStaffMemberRequest;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Rules\EmailRule;
use App\Modules\Identity\Http\Rules\PasswordRule;
use Illuminate\Validation\Rules\Password as LaravelPasswordRule;

it('keeps the email and password rules out of the account form requests', function (string $class, bool $choosesPassword) {
    $request = $class::create('/', 'POST');
    $request->setUserResolver(fn () => customer());

    $rules = $request->rules();
    $emailRules = $rules['email'];

    expect($emailRules)->not->toContain('email')
        ->and($emailRules)->not->toContain('max:255')
        ->and(collect($emailRules)->contains(fn ($rule) => $rule instanceof EmailRule))->toBeTrue();

    $passwordRules = $rules['password'];

    expect(collect($passwordRules)->contains(fn ($rule) => $rule instanceof LaravelPasswordRule))->toBeFalse()
        ->and(collect($passwordRules)->contains(fn ($rule) => $rule instanceof PasswordRule))->toBe($choosesPassword);
})->with([
    'RegisterRequest' => [RegisterRequest::class, true],
    'LoginRequest' => [LoginRequest::class, false],
    'StoreCustomerRequest' => [StoreCustomerRequest::class, true],
    'UpdateCustomerRequest' => [UpdateCustomerRequest::class, true],
    'UpdateProfileRequest' => [UpdateProfileRequest::class, true],
    'StoreStaffMemberRequest' => [StoreStaffMemberRequest::class, true],
    'UpdateStaffMemberRequest' => [UpdateStaffMemberRequest::class, true],
]);
