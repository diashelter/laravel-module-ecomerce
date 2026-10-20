<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Customers\Core\DTOs\CustomerSummaryDTO;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Ordering\Contracts\CustomerOrderHistory;

/**
 * Admin: an account with its order count and most recent orders.
 */
final class ShowCustomerUseCase
{
    public function __construct(
        private readonly CustomerAccounts $accounts,
        private readonly CustomerOrderHistory $orders,
    ) {}

    /**
     * @throws ModelNotFoundException when there is no such customer
     */
    public function execute(string $customerId, int $recentOrdersLimit): CustomerSummaryDTO
    {
        $account = $this->accounts->findProfile($customerId)
            ?? throw (new ModelNotFoundException)->setModel(CustomerProfile::class, [$customerId]);

        return new CustomerSummaryDTO(
            $account,
            $this->orders->countForCustomer($account->id),
            $this->orders->recentForCustomer($account->id, $recentOrdersLimit),
        );
    }
}
