<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Enums;

enum ProductStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Ativo',
            self::Inactive => 'Inativo',
        };
    }
}
