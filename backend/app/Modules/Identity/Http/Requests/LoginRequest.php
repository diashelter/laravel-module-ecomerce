<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\DTOs\LoginCredentialsDTO;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function toDto(): LoginCredentialsDTO
    {
        return new LoginCredentialsDTO(
            email: $this->validated('email'),
            password: $this->validated('password'),
        );
    }
}
