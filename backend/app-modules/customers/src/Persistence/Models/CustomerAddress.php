<?php

declare(strict_types=1);

namespace Modules\Customers\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Customers\Database\Factories\CustomerAddressFactory;
use Modules\Ordering\Core\Enums\BrazilianState;

/**
 * One address of a customer's address book. Orders never point here: they keep a copy.
 */
#[Fillable(['customer_id', 'recipient_name', 'postal_code', 'street', 'number', 'complement', 'district', 'city', 'state'])]
#[UseFactory(CustomerAddressFactory::class)]
class CustomerAddress extends Model
{
    /** @use HasFactory<CustomerAddressFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return ['state' => BrazilianState::class];
    }
}
