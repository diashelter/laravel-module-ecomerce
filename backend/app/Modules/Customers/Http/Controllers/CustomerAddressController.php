<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Http\Requests\CustomerAddressRequest;
use App\Modules\Customers\Http\Resources\CustomerAddressResource;
use App\Modules\Customers\Repositories\CustomerAddressRepository;
use App\Modules\Customers\UseCases\CreateCustomerAddressUseCase;
use App\Modules\Customers\UseCases\DeleteCustomerAddressUseCase;
use App\Modules\Customers\UseCases\UpdateCustomerAddressUseCase;
use App\Modules\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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
        $address = $createAddress->execute($request->user('customer')->getAuthIdentifier(), $request->toDto());

        return CustomerAddressResource::make($address)
            ->additional(['message' => 'Endereço cadastrado com sucesso.'])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(CustomerAddressRequest $request, string $address, UpdateCustomerAddressUseCase $updateAddress): CustomerAddressResource
    {
        return CustomerAddressResource::make($updateAddress->execute($request->user('customer')->getAuthIdentifier(), $address, $request->toDto()))
            ->additional(['message' => 'Endereço atualizado com sucesso.']);
    }

    public function destroy(Request $request, string $address, DeleteCustomerAddressUseCase $deleteAddress): Response
    {
        $deleteAddress->execute($request->user('customer')->getAuthIdentifier(), $address);

        return response()->noContent();
    }
}
