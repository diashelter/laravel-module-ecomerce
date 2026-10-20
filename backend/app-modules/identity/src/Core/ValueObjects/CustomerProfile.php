<?php

declare(strict_types=1);

namespace Modules\Identity\Core\ValueObjects;

use Carbon\CarbonImmutable;

/**
 * A shopper account as identity hands it to another module (see CustomerAccounts):
 * no credentials, never the model.
 */
final readonly class CustomerProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public CarbonImmutable $createdAt,
    ) {}
}
