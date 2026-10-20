<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests;

use Modules\Identity\Core\DTOs\LoginCredentialsDTO;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use Modules\Identity\Http\Rules\EmailRule;
use Modules\Shared\Http\Requests\ApiFormRequest;

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
