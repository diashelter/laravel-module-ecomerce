<?php

use App\Modules\Customers\DTOs\CustomerAddressDTO;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\UseCases\CreateCustomerAddressUseCase;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Shared\Exceptions\BusinessRuleException;

function addressData(): CustomerAddressDTO
{
    return new CustomerAddressDTO('Ana Souza', '01310100', 'Avenida Paulista', '1000', null, 'Bela Vista', 'São Paulo', BrazilianState::SP);
}

it('accepts the tenth address in the use case', function () {
    $user = customer();
    CustomerAddress::factory()->count(9)->create(['customer_id' => $user->id]);

    $address = app(CreateCustomerAddressUseCase::class)->execute($user->id, addressData());

    expect($address->customer_id)->toBe($user->id)
        ->and(CustomerAddress::query()->where('customer_id', $user->id)->count())->toBe(10);
});

it('refuses the eleventh address in the use case', function () {
    $user = customer();
    CustomerAddress::factory()->count(10)->create(['customer_id' => $user->id]);

    expect(fn () => app(CreateCustomerAddressUseCase::class)->execute($user->id, addressData()))
        ->toThrow(BusinessRuleException::class, 'Você pode cadastrar até 10 endereços.');

    expect(CustomerAddress::query()->where('customer_id', $user->id)->count())->toBe(10);
});
