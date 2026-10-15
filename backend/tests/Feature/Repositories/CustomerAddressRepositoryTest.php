<?php

use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Ordering\Enums\BrazilianState;
use App\Modules\Ordering\ValueObjects\DeliveryAddress;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
        'unknown id' => $repository->find($owner->id, unknownId()),
    };

    if ($case !== 'own address') {
        expect($found)->toBeNull();

        return;
    }

    expect($found)->toEqual(new DeliveryAddress('Ana Souza', '20040020', 'Rua da Assembleia', '10', 'Sala 5', 'Centro', 'Rio de Janeiro', BrazilianState::RJ))
        ->and($found->state)->toBe(BrazilianState::RJ);
})->with(['own address', 'address of another customer', 'unknown id']);

it('changes an address only in the customer own book', function (string $case) {
    $owner = customer();
    $address = addressOf($owner, ['city' => 'Campinas']);
    $repository = app(CustomerAddressRepository::class);
    $changes = ['city' => 'Hacked'];

    if ($case === 'own address') {
        expect($repository->updateForCustomer($owner->id, $address->id, $changes)->city)->toBe('Hacked')
            ->and($address->fresh()->city)->toBe('Hacked');

        $repository->deleteForCustomer($owner->id, $address->id);

        expect($address->fresh())->toBeNull();

        return;
    }

    [$customerId, $addressId] = match ($case) {
        'address of another customer' => [customer()->id, $address->id],
        'unknown id' => [$owner->id, unknownId()],
    };

    expect(fn () => $repository->updateForCustomer($customerId, $addressId, $changes))->toThrow(ModelNotFoundException::class)
        ->and(fn () => $repository->deleteForCustomer($customerId, $addressId))->toThrow(ModelNotFoundException::class)
        ->and($address->fresh()->city)->toBe('Campinas');
})->with(['own address', 'address of another customer', 'unknown id']);
