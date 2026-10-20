<?php

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Modules\Customers\Http\Resources\CustomerProfileResource as CustomersCustomerProfileResource;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Identity\Http\Resources\CustomerProfileResource as IdentityCustomerProfileResource;

// Identity (the store session) and customers (account and admin screens) each render the account
// with their own resource, and the API keeps one shape of it.
it('renders the customer account in the same shape in identity and customers', function () {
    $profile = new CustomerProfile(id: '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a', name: 'Ana Souza', email: 'ana@example.com', createdAt: CarbonImmutable::parse('2026-10-09 14:30:00'));
    $request = Request::create('/');

    $identity = IdentityCustomerProfileResource::make($profile)->resolve($request);
    $customers = CustomersCustomerProfileResource::make($profile)->resolve($request);

    expect($customers)->toBe($identity)
        ->and($customers)->toBe([
            'id' => '0199e3b0-4c2a-7d10-9a3b-5f6e7d8c9b0a',
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'created_at' => $profile->createdAt->toIso8601String(),
        ]);
});
