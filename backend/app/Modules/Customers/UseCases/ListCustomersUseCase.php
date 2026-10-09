<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\Contracts\CustomerAccounts;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Ordering\Repositories\OrderRepository;
use App\Modules\Ordering\ValueObjects\CustomerIds;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Admin: lists the accounts with their order count. Two queries per page (accounts, then the
 * counts of that page grouped by customer), whatever the page size.
 */
final class ListCustomersUseCase
{
    public function __construct(
        private readonly CustomerAccounts $accounts,
        private readonly OrderRepository $orders,
    ) {}

    /** @return LengthAwarePaginator<int, CustomerSummaryDTO> */
    public function execute(int $perPage): LengthAwarePaginator
    {
        $page = $this->accounts->paginateNewestFirst($perPage);
        $counts = $this->orders->countPerCustomer(new CustomerIds(...$page->getCollection()->pluck('id')->all()));

        return $page->through(fn (CustomerProfile $account) => new CustomerSummaryDTO($account, $counts->countFor($account->id)));
    }
}
