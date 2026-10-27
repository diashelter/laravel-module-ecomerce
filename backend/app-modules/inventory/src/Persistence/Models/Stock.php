<?php

declare(strict_types=1);

namespace Modules\Inventory\Persistence\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Core\ValueObjects\StockRecord;
use Modules\Inventory\Database\Factories\StockFactory;

#[Fillable(['product_id', 'quantity', 'version'])]
#[UseFactory(StockFactory::class)]
class Stock extends Model
{
    /** @use HasFactory<StockFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'version' => 'integer',
        ];
    }

    /** The data of this row, for the callers that must not hold the model. */
    public function toRecord(): StockRecord
    {
        return new StockRecord($this->id, $this->product_id, $this->quantity, $this->updated_at?->toImmutable(), $this->version);
    }

    public function hasUnits(): bool
    {
        return $this->quantity > 0;
    }
}
