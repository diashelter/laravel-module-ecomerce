<?php

use App\Modules\Customers\Models\CustomerAddress;

const ADDRESS_KEYS = ['id', 'recipient_name', 'postal_code', 'street', 'number', 'complement', 'district', 'city', 'state', 'created_at'];

it('lists no addresses for a new customer', function () {
    $this->actingAs(customer())->getJson('/api/account/addresses')
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('creates an address in the customer address book', function () {
    $user = customer();

    $response = $this->actingAs($user)->postJson('/api/account/addresses', addressPayload())
        ->assertCreated()
        ->assertJsonPath('message', 'Endereço cadastrado com sucesso.')
        ->assertJsonPath('data.postal_code', '01310100')
        ->assertJsonPath('data.recipient_name', 'Ana Souza')
        ->assertJsonPath('data.street', 'Avenida Paulista')
        ->assertJsonPath('data.number', '1000')
        ->assertJsonPath('data.complement', 'Apto 12')
        ->assertJsonPath('data.district', 'Bela Vista')
        ->assertJsonPath('data.city', 'São Paulo')
        ->assertJsonPath('data.state', 'SP');

    expect(array_keys($response->json('data')))->toBe(ADDRESS_KEYS)
        ->and(CustomerAddress::query()->count())->toBe(1)
        ->and(CustomerAddress::query()->sole()->customer_id)->toBe($user->id);
});

it('normalizes the state and an empty complement', function (array $override, string $state, ?string $complement, bool $omitComplement = false) {
    $user = customer();
    $payload = addressPayload($override);

    if ($omitComplement) {
        unset($payload['complement']);
    }

    $this->actingAs($user)->postJson('/api/account/addresses', $payload)
        ->assertCreated()
        ->assertJsonPath('data.state', $state)
        ->assertJsonPath('data.complement', $complement);

    $stored = CustomerAddress::query()->sole();
    expect($stored->state->value)->toBe($state)->and($stored->complement)->toBe($complement);
})->with([
    'state typed in lower case with spaces' => [['state' => ' sp '], 'SP', 'Apto 12'],
    'complement omitted' => [[], 'SP', null, true],
    'complement null' => [['complement' => null], 'SP', null],
    'complement empty' => [['complement' => ''], 'SP', null],
]);

it('lists only the customer addresses, most recent first', function () {
    $user = customer();
    $first = addressOf($user, ['street' => 'Rua Primeira']);
    $second = addressOf($user, ['street' => 'Rua Segunda']);
    addressOf(customer());

    $response = $this->actingAs($user)->getJson('/api/account/addresses')->assertOk()->assertJsonCount(2, 'data');

    expect($response->json('data.*.id'))->toBe([$second->id, $first->id]);
});

it('validates the postal code format', function (string $postalCode, bool $accepted) {
    $user = customer();
    $response = $this->actingAs($user)->postJson('/api/account/addresses', addressPayload(['postal_code' => $postalCode]));

    if ($accepted) {
        $response->assertCreated();
        expect(CustomerAddress::query()->sole()->postal_code)->toBe('01310100');

        return;
    }

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'VALIDATION_FAILED')
        ->assertJsonValidationErrors('postal_code');
    expect(CustomerAddress::query()->count())->toBe(0);
})->with([
    'seven digits' => ['1234567', false],
    'hyphen in the wrong place' => ['0131-0100', false],
    'a letter' => ['0131010a', false],
    'a space' => ['01310 100', false],
    'eight digits' => ['01310100', true],
    'hyphenated' => ['01310-100', true],
]);

it('rejects a state outside the 27 states', function (array $override) {
    $payload = addressPayload($override);

    if (! array_key_exists('state', $override)) {
        unset($payload['state']);
    }

    $this->actingAs(customer())->postJson('/api/account/addresses', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('state');

    expect(CustomerAddress::query()->count())->toBe(0);
})->with([
    'unknown state' => [['state' => 'XX']],
    'empty state' => [['state' => '']],
    'missing state' => [[]],
]);

it('requires every address field but the complement', function (string $field, bool $sent) {
    $payload = addressPayload();

    $sent ? $payload[$field] = '' : $payload = array_diff_key($payload, [$field => true]);

    $this->actingAs(customer())->postJson('/api/account/addresses', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(CustomerAddress::query()->count())->toBe(0);
})->with(function () {
    foreach (['recipient_name', 'street', 'number', 'district', 'city'] as $field) {
        yield "{$field} missing" => [$field, false];
        yield "{$field} empty" => [$field, true];
    }
});

it('limits the length of each address field', function (string $field, int $limit) {
    $this->actingAs(customer());

    $this->postJson('/api/account/addresses', addressPayload([$field => str_repeat('a', $limit)]))->assertCreated();

    $this->postJson('/api/account/addresses', addressPayload([$field => str_repeat('a', $limit + 1)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(CustomerAddress::query()->count())->toBe(1);
})->with([
    'recipient_name' => ['recipient_name', 120],
    'street' => ['street', 150],
    'number' => ['number', 20],
    'complement' => ['complement', 100],
    'district' => ['district', 100],
    'city' => ['city', 100],
]);

it('refuses an eleventh address', function () {
    $nineAddresses = customer();
    CustomerAddress::factory()->count(9)->create(['customer_id' => $nineAddresses->id]);

    $this->actingAs($nineAddresses)->postJson('/api/account/addresses', addressPayload())->assertCreated();
    expect(CustomerAddress::query()->where('customer_id', $nineAddresses->id)->count())->toBe(10);

    $tenAddresses = customer();
    CustomerAddress::factory()->count(10)->create(['customer_id' => $tenAddresses->id]);

    $response = $this->actingAs($tenAddresses)->postJson('/api/account/addresses', addressPayload())->assertConflict();

    assertApiError($response, 'BUSINESS_RULE_VIOLATION', 'Você pode cadastrar até 10 endereços.');
    expect(CustomerAddress::query()->where('customer_id', $tenAddresses->id)->count())->toBe(10);
});

it('updates an address of the customer', function () {
    $user = customer();
    $address = addressOf($user, ['state' => 'SP', 'city' => 'São Paulo']);

    $this->actingAs($user)->putJson("/api/account/addresses/{$address->id}", addressPayload(['state' => 'RJ', 'city' => 'Rio de Janeiro']))
        ->assertOk()
        ->assertJsonPath('message', 'Endereço atualizado com sucesso.')
        ->assertJsonPath('data.id', $address->id)
        ->assertJsonPath('data.state', 'RJ')
        ->assertJsonPath('data.city', 'Rio de Janeiro');

    $stored = $address->fresh();
    expect($stored->state->value)->toBe('RJ')
        ->and($stored->city)->toBe('Rio de Janeiro')
        ->and($stored->postal_code)->toBe('01310100');
});

it('requires the full address on update', function () {
    $user = customer();
    $address = addressOf($user, ['city' => 'Campinas']);
    $payload = array_diff_key(addressPayload(), ['city' => true]);

    $this->actingAs($user)->putJson("/api/account/addresses/{$address->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('city');

    expect($address->fresh()->city)->toBe('Campinas');
});

it('deletes an address of the customer', function () {
    $user = customer();
    $address = addressOf($user);

    $this->actingAs($user)->deleteJson("/api/account/addresses/{$address->id}")->assertNoContent();

    expect(CustomerAddress::query()->whereKey($address->id)->exists())->toBeFalse();
});

it('forbids changing an address of another customer', function (string $method) {
    $owner = customer();
    $address = addressOf($owner, ['city' => 'Campinas']);

    $this->actingAs(customer())->json($method, "/api/account/addresses/{$address->id}", addressPayload(['city' => 'Hacked']))
        ->assertForbidden();

    expect(CustomerAddress::query()->count())->toBe(1)
        ->and($address->fresh()->city)->toBe('Campinas');
})->with(['put', 'delete']);

it('answers 404 to an unknown address', function (string $method) {
    $this->actingAs(customer())->json($method, '/api/account/addresses/999999', addressPayload())->assertNotFound();
})->with(['put', 'delete']);

it('answers 401 to a guest on every address route', function (string $method, string $uri) {
    $address = addressOf(customer());
    $uri = str_replace('{address}', (string) $address->id, $uri);

    $this->json($method, $uri, addressPayload())->assertUnauthorized();

    expect(CustomerAddress::query()->count())->toBe(1)
        ->and($address->fresh()->city)->toBe($address->city);
})->with([
    ['get', '/api/account/addresses'],
    ['post', '/api/account/addresses'],
    ['put', '/api/account/addresses/{address}'],
    ['delete', '/api/account/addresses/{address}'],
]);
