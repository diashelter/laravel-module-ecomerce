<?php

use App\Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput as CustomersNormalizesEmailInput;
use App\Modules\Customers\Http\Requests\Concerns\NormalizesStateInput as CustomersNormalizesStateInput;
use App\Modules\Customers\Http\Requests\CustomerAddressRequest;
use App\Modules\Customers\Http\Resources\CustomerProfileResource as CustomersCustomerProfileResource;
use App\Modules\Customers\Http\Rules\EmailRule as CustomersEmailRule;
use App\Modules\Customers\Http\Rules\PasswordRule as CustomersPasswordRule;
use App\Modules\Fulfillment\Http\Requests\Concerns\NormalizesStateInput as FulfillmentNormalizesStateInput;
use App\Modules\Fulfillment\Http\Requests\ShippingQuoteRequest;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;
use App\Modules\Ordering\Enums\BrazilianState;

/*
 * The Http folder of a module is private, so the customers and fulfillment modules have their own
 * adapters of the e-mail, password and state rules. They only translate: the limits, patterns
 * and the list of states stay in `Email`, `Password` and `BrazilianState`.
 */

const HTTP_ADAPTERS = [
    'Customers EmailRule' => [CustomersEmailRule::class],
    'Customers PasswordRule' => [CustomersPasswordRule::class],
    'Customers NormalizesEmailInput' => [CustomersNormalizesEmailInput::class],
    'Customers NormalizesStateInput' => [CustomersNormalizesStateInput::class],
    'Customers CustomerProfileResource' => [CustomersCustomerProfileResource::class],
    'Fulfillment NormalizesStateInput' => [FulfillmentNormalizesStateInput::class],
];

it('writes no limit, pattern or state list in the http adapters', function (string $adapter) {
    $tokens = PhpToken::tokenize(file_get_contents((new ReflectionClass($adapter))->getFileName()));
    $inDeclare = false;

    foreach ($tokens as $token) {
        // `declare(strict_types=1)` is the file header every class has, not a rule of the adapter.
        if ($token->is(T_DECLARE) || $inDeclare) {
            $inDeclare = $token->text !== ';';

            continue;
        }

        expect($token->is([T_LNUMBER, T_DNUMBER]))->toBeFalse("{$adapter} writes the number {$token->text}");

        if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED])) {
            expect(str_starts_with(ltrim($token->text, '\\'), 'preg_'))->toBeFalse("{$adapter} calls {$token->text}");
        }

        if ($token->is(T_CONSTANT_ENCAPSED_STRING)) {
            $literal = substr($token->text, 1, -1);

            expect(preg_match('/^[A-Z]{2}$/', $literal))->toBe(0, "{$adapter} writes the state {$literal}")
                ->and(in_array(substr($literal, 0, 1), ['/', '#', '~'], true))->toBeFalse("{$adapter} writes the pattern {$literal}");
        }
    }
})->with(HTTP_ADAPTERS);

foreach ([
    'Customers EmailRule' => [CustomersEmailRule::class, Email::class],
    'Customers NormalizesEmailInput' => [CustomersNormalizesEmailInput::class, Email::class],
    'Customers PasswordRule' => [CustomersPasswordRule::class, Password::class],
    'Customers NormalizesStateInput' => [CustomersNormalizesStateInput::class, BrazilianState::class],
    'Fulfillment NormalizesStateInput' => [FulfillmentNormalizesStateInput::class, BrazilianState::class],
] as $name => [$adapter, $rule]) {
    arch("delegates the http adapters to the vocabulary: {$name} uses ".class_basename($rule))
        ->expect($adapter)
        ->toUse($rule);
}

it('normalizes the state with the trait of its own module', function () {
    expect(class_uses(CustomerAddressRequest::class))->toContain(CustomersNormalizesStateInput::class)
        ->and(class_uses(ShippingQuoteRequest::class))->toContain(FulfillmentNormalizesStateInput::class);
});
