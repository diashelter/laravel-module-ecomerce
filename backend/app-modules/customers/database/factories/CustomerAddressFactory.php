<?php

declare(strict_types=1);

namespace Modules\Customers\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Customers\Persistence\Models\CustomerAddress;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Ordering\Core\Enums\BrazilianState;

/**
 * @extends Factory<CustomerAddress>
 */
class CustomerAddressFactory extends Factory
{
    protected $model = CustomerAddress::class;

    public function definition(): array
    {
        return [
            'customer_id' => CustomerAccount::factory(),
            'recipient_name' => fake()->name(),
            'postal_code' => fake()->numerify('########'),
            'street' => fake()->streetName(),
            'number' => (string) fake()->numberBetween(1, 9999),
            'complement' => null,
            'district' => fake()->words(2, true),
            'city' => fake()->city(),
            'state' => BrazilianState::SP,
        ];
    }

    public function inState(BrazilianState $state): static
    {
        return $this->state(fn () => ['state' => $state]);
    }
}
