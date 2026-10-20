<?php

declare(strict_types=1);

namespace Modules\Catalog\Core\Services;

use Modules\Shared\Exceptions\BusinessRuleException;

class CategoryService
{
    /**
     * @throws BusinessRuleException
     */
    public function ensureCanBeDeleted(bool $hasProducts): void
    {
        if ($hasProducts) {
            throw new BusinessRuleException('Esta categoria possui produtos associados e não pode ser excluída.');
        }
    }
}
