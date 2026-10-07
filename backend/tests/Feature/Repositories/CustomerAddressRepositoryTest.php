<?php

use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;

it('finds a delivery address only in the customer own book', function (string $case) {
    $owner = customer();
    $address = addressOf($owner, [
        'recipient_name' => 'Ana Souza',
        'postal_code' => '20040020',
        'street' => 'Rua da Assembleia',
        'number' => '10',
        'complement' => 'Sala 5',
        'district' => 'Centro',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
    ]);
    $repository = app(CustomerAddressRepository::class);

    $found = match ($case) {
        'own address' => $repository->find($owner->id, $address->id),
        'address of another customer' => $repository->find(customer()->id, $address->id),
        'unknown id' => $repository->find($owner->id, 999999),
    };

    if ($case !== 'own address') {
        expect($found)->toBeNull();

        return;
    }

    expect($found)->toEqual(new DeliveryAddress('Ana Souza', '20040020', 'Rua da Assembleia', '10', 'Sala 5', 'Centro', 'Rio de Janeiro', BrazilianState::RJ))
        ->and($found->state)->toBe(BrazilianState::RJ);
})->with(['own address', 'address of another customer', 'unknown id']);
