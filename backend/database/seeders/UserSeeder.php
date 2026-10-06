<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const CUSTOMERS = 10;

    public function run(): void
    {
        // Administrators are created only here (never through the API).
        $admin = User::query()->firstOrNew(['email' => 'admin@example.com']);
        $admin->forceFill([
            'name' => 'Admin',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ])->save();

        // A known customer account to make manual testing easier.
        $customer = User::query()->firstOrNew(['email' => 'cliente@example.com']);
        $customer->forceFill([
            'name' => 'Cliente Demo',
            'password' => UserFactory::DEFAULT_PASSWORD,
            'role' => UserRole::Customer,
            'email_verified_at' => now(),
        ])->save();

        $missing = self::CUSTOMERS - User::query()->customers()->count();

        if ($missing > 0) {
            User::factory()->customer()->count($missing)->create();
        }
    }
}
