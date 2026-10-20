<?php

declare(strict_types=1);

namespace Modules\Customers\Core\Services;

use Modules\Shared\Exceptions\BusinessRuleException;

/**
 * Address book rules. No database access here: the caller passes what it counted.
 */
class CustomerAddressService
{
    public const MAX_ADDRESSES = 10;

    /**
     * @throws BusinessRuleException
     */
    public function assertHasRoomForAnother(int $currentCount): void
    {
        if ($currentCount >= self::MAX_ADDRESSES) {
            throw new BusinessRuleException('Você pode cadastrar até '.self::MAX_ADDRESSES.' endereços.');
        }
    }
}
