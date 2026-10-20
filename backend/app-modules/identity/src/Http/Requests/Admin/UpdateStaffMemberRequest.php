<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Identity\Core\DTOs\UpdateStaffMemberDTO;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use Modules\Identity\Http\Rules\EmailRule;
use Modules\Identity\Http\Rules\PasswordRule;
use Modules\Shared\Http\Requests\ApiFormRequest;

class UpdateStaffMemberRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', new EmailRule, Rule::unique('users', 'email')->ignore($this->route('user'))],
            'password' => ['nullable', 'string', 'confirmed', new PasswordRule],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function toDto(): UpdateStaffMemberDTO
    {
        $password = $this->validated('password');

        return new UpdateStaffMemberDTO(
            profile: new UpdateUserProfileDTO(
                name: $this->validated('name'),
                email: new Email($this->validated('email')),
                password: $password ? new Password($password) : null,
            ),
            role: UserRole::from($this->validated('role')),
        );
    }
}
