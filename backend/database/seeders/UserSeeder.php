<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Database\Factories\UserFactory;
use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Identity\Persistence\Models\User;

class UserSeeder extends Seeder
{
    public const CUSTOMERS = 10;

    public function run(): void
    {
        // Staff members are created only here and through the admin staff screen.
        foreach ([
            ['admin@example.com', 'Admin', UserRole::Admin],
            ['suporte@example.com', 'Suporte', UserRole::Support],
        ] as [$email, $name, $role]) {
            $member = User::query()->firstOrNew(['email' => $email]);
            $member->forceFill([
                'name' => $name,
                'password' => UserFactory::DEFAULT_PASSWORD,
                'role' => $role,
                'email_verified_at' => now(),
            ])->save();
        }

        // A known customer account to make manual testing easier.
        $customer = CustomerAccount::query()->firstOrNew(['email' => 'cliente@example.com']);
        $customer->forceFill([
            'name' => 'Cliente Demo',
            'password' => UserFactory::DEFAULT_PASSWORD,
        ])->save();

        $missing = self::CUSTOMERS - CustomerAccount::query()->count();

        if ($missing > 0) {
            CustomerAccount::factory()->count($missing)->create();
        }
    }
}
