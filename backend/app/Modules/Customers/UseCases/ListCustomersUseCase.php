<?php

declare(strict_types=1);

namespace App\Modules\Customers\UseCases;

use App\Modules\Customers\DTOs\CustomerSummaryDTO;
use App\Modules\Identity\Models\CustomerAccount;
use App\Modules\Identity\Repositories\CustomerAccountRepository;
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
        private readonly CustomerAccountRepository $accounts,
        private readonly OrderRepository $orders,
    ) {}

    /** @return LengthAwarePaginator<int, CustomerSummaryDTO> */
    public function execute(int $perPage): LengthAwarePaginator
    {
        $page = $this->accounts->paginateNewestFirst($perPage);
        $counts = $this->orders->countPerCustomer(new CustomerIds(...$page->getCollection()->modelKeys()));

        return $page->through(fn (CustomerAccount $account) => new CustomerSummaryDTO($account, $counts->countFor($account->id)));
    }
}
