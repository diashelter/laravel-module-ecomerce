<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Services;

use App\Modules\Shared\Exceptions\BusinessRuleException;

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
