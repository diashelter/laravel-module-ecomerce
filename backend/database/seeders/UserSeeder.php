<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

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
