<?php

declare(strict_types=1);

namespace App\Modules\Ordering\Enums;

enum OrderStatus: string
{
    case Placed = 'placed';
    case AwaitingPayment = 'awaiting_payment';
    case PaymentApproved = 'payment_approved';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Placed => 'Pedido efetuado',
            self::AwaitingPayment => 'Aguardando pagamento',
            self::PaymentApproved => 'Pagamento aprovado',
            self::Delivered => 'Pedido entregue',
        };
    }

    /**
     * Position of the status in the order lifecycle (used by the timeline).
     */
    public function step(): int
    {
        return match ($this) {
            self::Placed => 1,
            self::AwaitingPayment => 2,
            self::PaymentApproved => 3,
            self::Delivered => 4,
        };
    }
}
