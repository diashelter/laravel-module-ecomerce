<?php

declare(strict_types=1);

namespace Modules\Customers\Core\UseCases;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Customers\Core\DTOs\CustomerSummaryDTO;
use Modules\Identity\Contracts\CustomerAccounts;
use Modules\Identity\Core\ValueObjects\CustomerProfile;
use Modules\Ordering\Contracts\CustomerOrderHistory;
use Modules\Ordering\Core\ValueObjects\CustomerIds;

/**
 * Admin: lists the accounts with their order count. Two queries per page (accounts, then the
 * counts of that page grouped by customer), whatever the page size.
 */
final class ListCustomersUseCase
{
    public function __construct(
        private readonly CustomerAccounts $accounts,
        private readonly CustomerOrderHistory $orders,
    ) {}

    /** @return LengthAwarePaginator<int, CustomerSummaryDTO> */
    public function execute(int $perPage): LengthAwarePaginator
    {
        $page = $this->accounts->paginateNewestFirst($perPage);
        $counts = $this->orders->countPerCustomer(new CustomerIds(...$page->getCollection()->pluck('id')->all()));

        return $page->through(fn (CustomerProfile $account) => new CustomerSummaryDTO($account, $counts->countFor($account->id)));
    }
}
