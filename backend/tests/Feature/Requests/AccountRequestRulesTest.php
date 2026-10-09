<?php

use App\Modules\Customers\Http\Requests\Admin\StoreCustomerRequest;
use App\Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest;
use App\Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput as CustomersNormalizesEmailInput;
use App\Modules\Customers\Http\Requests\UpdateProfileRequest;
use App\Modules\Customers\Http\Rules\EmailRule as CustomersEmailRule;
use App\Modules\Customers\Http\Rules\PasswordRule as CustomersPasswordRule;
use App\Modules\Identity\Http\Requests\Admin\StoreStaffMemberRequest;
use App\Modules\Identity\Http\Requests\Admin\UpdateStaffMemberRequest;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput as IdentityNormalizesEmailInput;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterRequest;
use App\Modules\Identity\Http\Rules\EmailRule as IdentityEmailRule;
use App\Modules\Identity\Http\Rules\PasswordRule as IdentityPasswordRule;
use Illuminate\Validation\Rules\Password as LaravelPasswordRule;

/** @return list<class-string> the classes of the module e-mail and password rules among these rules */
function accountRuleClasses(array $rules): array
{
    return collect($rules)
        ->filter(fn ($rule) => is_object($rule) && in_array($rule::class, [IdentityEmailRule::class, IdentityPasswordRule::class, CustomersEmailRule::class, CustomersPasswordRule::class], true))
        ->map(fn (object $rule) => $rule::class)
        ->values()
        ->all();
}

// Each module validates with its own adapters: the Http folder of a module is private.
it('keeps the email and password rules out of the account form requests', function (string $class, string $emailRule, ?string $passwordRule) {
    $request = $class::create('/', 'POST');
    $request->setUserResolver(fn () => customer());

    $rules = $request->rules();
    $emailRules = $rules['email'];

    expect($emailRules)->not->toContain('email')
        ->and($emailRules)->not->toContain('max:255')
        ->and(accountRuleClasses($emailRules))->toBe([$emailRule]);

    $passwordRules = $rules['password'];

    expect(collect($passwordRules)->contains(fn ($rule) => $rule instanceof LaravelPasswordRule))->toBeFalse()
        ->and(accountRuleClasses($passwordRules))->toBe($passwordRule === null ? [] : [$passwordRule]);
})->with([
    'RegisterRequest' => [RegisterRequest::class, IdentityEmailRule::class, IdentityPasswordRule::class],
    'LoginRequest' => [LoginRequest::class, IdentityEmailRule::class, null],
    'StoreCustomerRequest' => [StoreCustomerRequest::class, CustomersEmailRule::class, CustomersPasswordRule::class],
    'UpdateCustomerRequest' => [UpdateCustomerRequest::class, CustomersEmailRule::class, CustomersPasswordRule::class],
    'UpdateProfileRequest' => [UpdateProfileRequest::class, CustomersEmailRule::class, CustomersPasswordRule::class],
    'StoreStaffMemberRequest' => [StoreStaffMemberRequest::class, IdentityEmailRule::class, IdentityPasswordRule::class],
    'UpdateStaffMemberRequest' => [UpdateStaffMemberRequest::class, IdentityEmailRule::class, IdentityPasswordRule::class],
]);

it('normalizes the email with the trait of its own module', function (string $class, string $trait, string $otherTrait) {
    expect(class_uses($class))->toContain($trait)
        ->and(class_uses($class))->not->toContain($otherTrait);
})->with([
    'RegisterRequest' => [RegisterRequest::class, IdentityNormalizesEmailInput::class, CustomersNormalizesEmailInput::class],
    'LoginRequest' => [LoginRequest::class, IdentityNormalizesEmailInput::class, CustomersNormalizesEmailInput::class],
    'StoreStaffMemberRequest' => [StoreStaffMemberRequest::class, IdentityNormalizesEmailInput::class, CustomersNormalizesEmailInput::class],
    'UpdateStaffMemberRequest' => [UpdateStaffMemberRequest::class, IdentityNormalizesEmailInput::class, CustomersNormalizesEmailInput::class],
    'StoreCustomerRequest' => [StoreCustomerRequest::class, CustomersNormalizesEmailInput::class, IdentityNormalizesEmailInput::class],
    'UpdateCustomerRequest' => [UpdateCustomerRequest::class, CustomersNormalizesEmailInput::class, IdentityNormalizesEmailInput::class],
    'UpdateProfileRequest' => [UpdateProfileRequest::class, CustomersNormalizesEmailInput::class, IdentityNormalizesEmailInput::class],
]);
