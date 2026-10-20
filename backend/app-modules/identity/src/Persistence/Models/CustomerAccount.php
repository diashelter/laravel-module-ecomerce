<?php

declare(strict_types=1);

namespace Modules\Identity\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Identity\Database\Factories\CustomerAccountFactory;

/**
 * The shopper's account, authenticated by the `customer` guard. Purchase history lives on the
 * ordering side, which references the account only by id (see Customer and Order::customer()).
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
#[UseFactory(CustomerAccountFactory::class)]
class CustomerAccount extends Authenticatable
{
    /** @use HasFactory<CustomerAccountFactory> */
    use HasFactory, HasUuids, Notifiable;

    protected $table = 'customers';

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    /**
     * What identity hands to the HTTP layer and to other modules: the account without credentials.
     */
    public function toProfile(): CustomerProfile
    {
        return new CustomerProfile($this->id, $this->name, $this->email, $this->created_at->toImmutable());
    }
}
