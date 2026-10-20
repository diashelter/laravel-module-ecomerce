<?php

declare(strict_types=1);

namespace Modules\Customers\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput;
use Modules\Customers\Http\Rules\EmailRule;
use Modules\Customers\Http\Rules\PasswordRule;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateCustomerRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', new EmailRule, Rule::unique('customers', 'email')->ignore($this->route('customer'))],
            'password' => ['nullable', 'string', 'confirmed', new PasswordRule],
        ];
    }

    public function toDto(): UpdateUserProfileDTO
    {
        $password = $this->validated('password');

        return new UpdateUserProfileDTO(
            name: $this->validated('name'),
            email: new Email($this->validated('email')),
            password: $password ? new Password($password) : null,
        );
    }
}
