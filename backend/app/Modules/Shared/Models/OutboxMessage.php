<?php

declare(strict_types=1);

namespace App\Modules\Shared\Models;

use Database\Factories\OutboxMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One event in the transactional outbox. Pending while published_at is null.
 */
#[Fillable(['name', 'version', 'payload', 'request_id', 'occurred_at', 'published_at'])]
#[UseFactory(OutboxMessageFactory::class)]
class OutboxMessage extends Model
{
    /** @use HasFactory<OutboxMessageFactory> */
    use HasFactory, HasUuids;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'payload' => 'array',
            'occurred_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
