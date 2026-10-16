<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Models;

use App\Modules\Inventory\ValueObjects\StockRecord;
use Database\Factories\StockFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['product_id', 'quantity'])]
#[UseFactory(StockFactory::class)]
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /** The data of this row, for the callers that must not hold the model. */
    public function toRecord(): StockRecord
    {
        return new StockRecord($this->id, $this->product_id, $this->quantity, $this->updated_at?->toImmutable());
    }

    public function hasUnits(): bool
    {
        return $this->quantity > 0;
    }
}
