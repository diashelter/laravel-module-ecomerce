<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Ordering\Contracts\CustomerOrderHistory;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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
