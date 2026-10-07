<?php

declare(strict_types=1);

namespace App\Modules\Payment\Enums;

/**
 * Why the gateway declined a charge. Gateway agnostic: each adapter maps its own codes here.
 */
enum DeclineReason: string
{
    case InsufficientFunds = 'insufficient_funds';
    case CardDeclined = 'card_declined';
    case InvalidCard = 'invalid_card';

    /** Message shown to the customer. */
    public function message(): string
    {
        return match ($this) {
            self::InsufficientFunds => 'Pagamento recusado: saldo insuficiente.',
            self::CardDeclined => 'Pagamento recusado pelo emissor do cartão.',
            self::InvalidCard => 'Cartão inválido.',
        };
    }
}
