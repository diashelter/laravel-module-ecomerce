<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Customer => 'Cliente',
        };
    }
}
