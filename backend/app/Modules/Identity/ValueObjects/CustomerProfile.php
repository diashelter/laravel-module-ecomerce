<?php

declare(strict_types=1);

namespace App\Modules\Identity\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * A shopper account as identity hands it to another module (see CustomerAccounts):
 * no credentials, never the model.
 */
final readonly class CustomerProfile
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public CarbonImmutable $createdAt,
    ) {}
}
