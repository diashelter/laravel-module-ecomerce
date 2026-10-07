<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Http\Requests\CustomerAddressRequest;
use App\Modules\Customers\Http\Resources\CustomerAddressResource;
use App\Modules\Customers\Models\CustomerAddress;
use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Customers\UseCases\CreateCustomerAddressUseCase;
use App\Modules\Customers\UseCases\DeleteCustomerAddressUseCase;
use App\Modules\Customers\UseCases\UpdateCustomerAddressUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The logged-in customer's own address book (guard `customer`).
 */
class CustomerAddressController extends Controller
{
    public function index(Request $request, CustomerAddressRepository $addresses): AnonymousResourceCollection
    {
        return CustomerAddressResource::collection($addresses->newestFirstForCustomer($request->user('customer')->id));
    }

    public function store(CustomerAddressRequest $request, CreateCustomerAddressUseCase $createAddress): JsonResponse
    {
        $address = $createAddress->execute($request->user('customer'), $request->toDto());

        return CustomerAddressResource::make($address)
            ->additional(['message' => 'Endereço cadastrado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(CustomerAddressRequest $request, CustomerAddress $address, UpdateCustomerAddressUseCase $updateAddress): CustomerAddressResource
    {
        return CustomerAddressResource::make($updateAddress->execute($address, $request->toDto()))
            ->additional(['message' => 'Endereço atualizado com sucesso.']);
    }

    public function destroy(CustomerAddress $address, DeleteCustomerAddressUseCase $deleteAddress): Response
    {
        Gate::authorize('delete', $address);

        $deleteAddress->execute($address);

        return response()->noContent();
    }
}
