<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests\Admin;

use Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput;
use Modules\Customers\Http\Rules\EmailRule;
use Modules\Customers\Http\Rules\PasswordRule;
use Modules\Identity\Core\DTOs\CreateUserDTO;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Shared\Http\Requests\ApiFormRequest;

class StoreCustomerRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

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
