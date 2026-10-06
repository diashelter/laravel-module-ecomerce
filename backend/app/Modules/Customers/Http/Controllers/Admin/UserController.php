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
use App\Modules\Identity\Models\User;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(ListCustomersUseCase $listCustomers): AnonymousResourceCollection
    {
        return CustomerSummaryResource::collection($listCustomers->execute(15));
    }

    /**
     * Admins can only create customers here; administrators come from the seeder.
     */
    public function store(StoreCustomerRequest $request, CreateCustomerUseCase $createCustomer): JsonResponse
    {
        $customer = $createCustomer->execute($request->toDto());

        return CustomerSummaryResource::make($customer)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(User $user, ShowCustomerUseCase $showCustomer): CustomerSummaryResource
    {
        return CustomerSummaryResource::make($showCustomer->execute($user, 10));
    }

    public function update(UpdateCustomerRequest $request, User $user, UpdateCustomerUseCase $updateCustomer): CustomerSummaryResource
    {
        Gate::authorize('update', $user);

        return CustomerSummaryResource::make($updateCustomer->execute($user, $request->toDto()));
    }
}
