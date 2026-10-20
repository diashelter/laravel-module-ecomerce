<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests;

use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use Modules\Identity\Http\Rules\EmailRule;
use Modules\Identity\Http\Rules\PasswordRule;
use Modules\Shared\Http\Requests\ApiFormRequest;

class RegisterRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

    /**
     * There is no "role" field: a registration only ever creates a shopper account.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', new EmailRule, 'unique:customers,email'],
            'password' => ['required', 'string', 'confirmed', new PasswordRule],
        ];
    }

    public function toDto(): CreateUserDTO
    {
        return new CreateUserDTO(
            name: $this->validated('name'),
            email: new Email($this->validated('email')),
            password: new Password($this->validated('password')),
        );
    }
}
