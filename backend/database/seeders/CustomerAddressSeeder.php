<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Ordering\Enums\BrazilianState;
use Illuminate\Database\Seeder;

/**
 * Address books for the demo customers, so a seeded customer can check out right away.
 * `cliente@example.com` keeps a single address in SP (2 business days, the shortest quote).
 */
class CustomerAddressSeeder extends Seeder
{
    public function run(): void
    {
        if (CustomerAddress::query()->exists()) {
            return;
        }

        foreach (CustomerAccount::query()->orderBy('id')->get() as $customer) {
            if ($customer->email === 'cliente@example.com') {
                CustomerAddress::factory()->create([
                    'customer_id' => $customer->id,
                    'recipient_name' => $customer->name,
                    'postal_code' => '01310100',
                    'street' => 'Avenida Paulista',
                    'number' => '1000',
                    'complement' => 'Apto 12',
                    'district' => 'Bela Vista',
                    'city' => 'São Paulo',
                    'state' => BrazilianState::SP,
                ]);

                continue;
            }

            CustomerAddress::factory()
                ->count(fake()->numberBetween(1, 2))
                ->state(fn () => ['state' => fake()->randomElement(BrazilianState::cases())])
                ->create(['customer_id' => $customer->id, 'recipient_name' => $customer->name]);
        }
    }
}
