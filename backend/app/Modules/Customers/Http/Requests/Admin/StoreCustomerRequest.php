<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests\Admin;

use App\Modules\Identity\DTOs\CreateUserDTO;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use App\Modules\Identity\Http\Rules\EmailRule;
use App\Modules\Identity\Http\Rules\PasswordRule;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;
use App\Modules\Shared\Http\Requests\ApiFormRequest;

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
