<?php

declare(strict_types=1);

namespace Modules\Identity\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Persistence\Models\User;

/**
 * Staff members (`users`). Shoppers come from CustomerAccountFactory.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Default password for every fake account (development/test only).
     */
    public const DEFAULT_PASSWORD = 'password';

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make(self::DEFAULT_PASSWORD),
            'role' => UserRole::Support,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function support(): static
    {
        return $this->state(fn () => ['role' => UserRole::Support]);
    }
}
