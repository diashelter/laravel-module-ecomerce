<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use Database\Factories\CustomerAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
    use HasFactory, Notifiable;

    protected $table = 'customers';

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }
}
