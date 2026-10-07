<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * Role of a staff member (`users`). Shoppers have no role: they live in `customers`.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Support = 'support';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Support => 'Suporte',
        };
    }
}
