<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Http\Requests\UpdateProfileRequest;
use App\Modules\Customers\UseCases\UpdateOwnProfileUseCase;
use App\Modules\Identity\Http\Resources\CustomerAccountResource;
use App\Modules\Shared\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateOwnProfileUseCase $updateOwnProfile): CustomerAccountResource
    {
        $account = $updateOwnProfile->execute($request->user('customer'), $request->toDto());

        return CustomerAccountResource::make($account)->additional(['message' => 'Dados atualizados com sucesso.']);
    }
}
