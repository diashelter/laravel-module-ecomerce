<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends ApiFormRequest
{
    /**
     * There is deliberately no "role" field: every registered user is a customer.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    public function toDto(): CreateUserDTO
    {
        return new CreateUserDTO(
            name: $this->validated('name'),
            email: $this->validated('email'),
            password: $this->validated('password'),
        );
    }
}
