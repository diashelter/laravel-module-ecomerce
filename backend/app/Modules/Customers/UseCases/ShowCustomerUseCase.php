<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Ordering\Repositories\OrderRepository;

/**
 * Admin: an account with its order count and most recent orders.
 */
final class ShowCustomerUseCase
{
    public function __construct(private readonly OrderRepository $orders) {}

    public function execute(CustomerProfile $account, int $recentOrdersLimit): CustomerSummaryDTO
    {
        return new CustomerSummaryDTO(
            $account,
            $this->orders->countForCustomer($account->id),
            $this->orders->recentForCustomer($account->id, $recentOrdersLimit),
        );
    }
}
