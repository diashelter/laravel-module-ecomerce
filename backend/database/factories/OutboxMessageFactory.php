<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Shared\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A pending message of a test event, unless a state says otherwise.
 *
 * @extends Factory<OutboxMessage>
 */
class OutboxMessageFactory extends Factory
{
    protected $model = OutboxMessage::class;

    public function definition(): array
    {
        return [
            'name' => 'testing.sample_event',
            'version' => 1,
            'payload' => ['order_id' => Str::uuid7()->toString()],
            'request_id' => null,
            'occurred_at' => now(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['published_at' => now()]);
    }
}
