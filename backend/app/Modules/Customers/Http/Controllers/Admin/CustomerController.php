<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers\Admin;

use App\Modules\Customers\Http\Requests\Admin\StoreCustomerRequest;
use App\Modules\Customers\Http\Requests\Admin\UpdateCustomerRequest;
use App\Modules\Customers\Http\Resources\CustomerSummaryResource;
use App\Modules\Customers\UseCases\CreateCustomerUseCase;
use App\Modules\Customers\UseCases\ListCustomersUseCase;
use App\Modules\Customers\UseCases\ShowCustomerUseCase;
use App\Modules\Customers\UseCases\UpdateCustomerUseCase;
use App\Modules\Identity\ValueObjects\CustomerProfile;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function show(CustomerProfile $customer, ShowCustomerUseCase $showCustomer): CustomerSummaryResource
    {
        return CustomerSummaryResource::make($showCustomer->execute($customer, 10));
    }

    public function update(UpdateCustomerRequest $request, CustomerProfile $customer, UpdateCustomerUseCase $updateCustomer): CustomerSummaryResource
    {
        return CustomerSummaryResource::make($updateCustomer->execute($customer->id, $request->toDto()));
    }
}
