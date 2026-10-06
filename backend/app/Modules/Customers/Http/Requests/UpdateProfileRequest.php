<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            // Changing the password requires the current one.
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function toDto(): UpdateUserProfileDTO
    {
        return new UpdateUserProfileDTO(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password') ?: null,
        );
    }
}
