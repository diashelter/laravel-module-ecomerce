<?php

declare(strict_types=1);

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Policies\CustomerAddressPolicy;
use App\Modules\Ordering\Enums\BrazilianState;
use Database\Factories\CustomerAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One address of a customer's address book. Orders never point here: they keep a copy.
 */
#[Fillable(['customer_id', 'recipient_name', 'postal_code', 'street', 'number', 'complement', 'district', 'city', 'state'])]
#[UseFactory(CustomerAddressFactory::class)]
#[UsePolicy(CustomerAddressPolicy::class)]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['state' => BrazilianState::class];
    }
}
