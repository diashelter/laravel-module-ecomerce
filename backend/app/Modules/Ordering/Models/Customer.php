<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * The buyer, as seen by the ordering side: a read-only projection of the `users` table.
 *
 * Login, password and role belong to User (identity). An order only needs to know who placed
 * it (`orders.user_id`) and that person's name and e-mail, so this model never writes:
 * accounts are created and changed through User.
 */
class Customer extends Model
{
    protected $table = 'users';

    /** Only the data the ordering side needs, never credentials. */
    protected $visible = ['id', 'name', 'email'];

    protected static function booted(): void
    {
        $preventWrites = function (): never {
            throw new LogicException('Customer is read-only. Change the account through User.');
        };

        static::saving($preventWrites);
        static::deleting($preventWrites);
    }
}
