<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\DTOs\LoginCredentialsDTO;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use App\Modules\Identity\Http\Rules\EmailRule;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

class LoginRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

    public function rules(): array
    {
        return [
            'email' => ['required', 'string', new EmailRule],
            'password' => ['required', 'string'],
        ];
    }

    public function toDto(): LoginCredentialsDTO
    {
        return new LoginCredentialsDTO(
            email: new Email($this->validated('email')),
            password: $this->validated('password'),
        );
    }
}
