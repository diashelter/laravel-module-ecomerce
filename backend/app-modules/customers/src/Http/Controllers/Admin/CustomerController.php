<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Modules\Customers\Core\UseCases\CreateCustomerUseCase;
use Modules\Customers\Core\UseCases\ListCustomersUseCase;
use Modules\Customers\Core\UseCases\ShowCustomerUseCase;
use Modules\Customers\Core\UseCases\UpdateCustomerUseCase;
use Modules\Customers\Http\Requests\Admin\StoreCustomerRequest;
use Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest;
use Modules\Customers\Http\Resources\CustomerSummaryResource;
use Modules\Shared\Http\Controllers\Controller;

class CustomerController extends Controller
{
    public function index(ListCustomersUseCase $listCustomers): AnonymousResourceCollection
    {
        return CustomerSummaryResource::collection($listCustomers->execute(15));
    }

    /**
     * Both staff roles can create customers.
     */
    public function store(StoreCustomerRequest $request, CreateCustomerUseCase $createCustomer): JsonResponse
    {
        $customer = $createCustomer->execute($request->toDto());

        return CustomerSummaryResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $customer, ShowCustomerUseCase $showCustomer): CustomerSummaryResource
    {
        return CustomerSummaryResource::make($showCustomer->execute($customer, 10));
    }

    public function update(UpdateCustomerRequest $request, string $customer, UpdateCustomerUseCase $updateCustomer): CustomerSummaryResource
    {
        return CustomerSummaryResource::make($updateCustomer->execute($customer, $request->toDto()));
    }
}
