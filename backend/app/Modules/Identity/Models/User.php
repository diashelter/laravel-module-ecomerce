<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Policies\UserPolicy;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Identity: who can log in and with which role. Purchase history lives on the ordering
 * side, which references the account only by id (see Customer and Order::customer()).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(UserFactory::class)]
#[UsePolicy(UserPolicy::class)]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * `role` is intentionally not fillable: it can never be set through mass assignment
     * (e.g. a malicious "role": "admin" in a registration payload).
     */
    protected $attributes = [
        'role' => 'customer',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    /** @param Builder<User> $query */
    public function scopeCustomers(Builder $query): void
    {
        $query->where('role', UserRole::Customer);
    }
}
