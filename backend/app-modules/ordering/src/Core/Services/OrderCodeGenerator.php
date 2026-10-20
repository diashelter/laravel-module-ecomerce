<?php

declare(strict_types=1);

namespace Modules\Ordering\Core\Services;

/**
 * Draws the code of an order: 8 symbols people can read out loud (K7Q2M9XA). The alphabet leaves
 * out 0, O, 1, I and L, so no symbol is mistaken for another. It is independent of the order id
 * and may collide: the unique index of `orders.code` is what decides, and PlaceOrderUseCase draws
 * again. Resolved by the container, so a test can replace it with a fixed sequence.
 */
class OrderCodeGenerator
{
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const LENGTH = 8;

    public function generate(): string
    {
        $code = '';

        for ($symbol = 0; $symbol < self::LENGTH; $symbol++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
