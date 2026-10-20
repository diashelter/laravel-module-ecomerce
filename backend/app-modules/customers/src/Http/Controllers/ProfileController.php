<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Controllers;

use Modules\Customers\Core\UseCases\UpdateOwnProfileUseCase;
use Modules\Customers\Http\Requests\UpdateProfileRequest;
use Modules\Customers\Http\Resources\CustomerProfileResource;
use Modules\Shared\Http\Controllers\Controller;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateOwnProfileUseCase $updateOwnProfile): CustomerProfileResource
    {
        $profile = $updateOwnProfile->execute($request->user('customer')->getAuthIdentifier(), $request->toDto());

        return CustomerProfileResource::make($profile)->additional(['message' => 'Dados atualizados com sucesso.']);
    }
}
