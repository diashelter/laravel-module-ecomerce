<?php

use App\Modules\Customers\Http\Resources\CustomerProfileResource as CustomersCustomerProfileResource;
use App\Modules\Identity\Http\Resources\CustomerProfileResource as IdentityCustomerProfileResource;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

// Identity (the store session) and customers (account and admin screens) each render the account
// with their own resource, and the API keeps one shape of it.
it('renders the customer account in the same shape in identity and customers', function () {
    $profile = new CustomerProfile(id: 7, name: 'Ana Souza', email: 'ana@example.com', createdAt: CarbonImmutable::parse('2026-10-09 14:30:00'));
    $request = Request::create('/');

    $identity = IdentityCustomerProfileResource::make($profile)->resolve($request);
    $customers = CustomersCustomerProfileResource::make($profile)->resolve($request);

    expect($customers)->toBe($identity)
        ->and($customers)->toBe([
            'id' => 7,
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'created_at' => $profile->createdAt->toIso8601String(),
        ]);
});
