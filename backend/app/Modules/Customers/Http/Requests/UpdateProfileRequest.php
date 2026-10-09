<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Http\Requests\Concerns\NormalizesEmailInput;
use App\Modules\Customers\Http\Rules\EmailRule;
use App\Modules\Customers\Http\Rules\PasswordRule;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', new EmailRule, Rule::unique('customers', 'email')->ignore($this->user('customer')->id)],
            // Changing the password requires the current one.
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
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
