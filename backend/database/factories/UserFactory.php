<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Default password for every fake user (development/test only).
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
            'role' => UserRole::Customer,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['role' => UserRole::Customer]);
    }
}
