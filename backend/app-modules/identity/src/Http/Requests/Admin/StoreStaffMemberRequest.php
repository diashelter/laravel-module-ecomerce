<?php

declare(strict_types=1);

namespace Modules\Identity\Http\Requests\Admin;

use Illuminate\Validation\Rule;
use Modules\Identity\Core\DTOs\CreateStaffMemberDTO;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;
use Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use Modules\Identity\Http\Rules\EmailRule;
use Modules\Identity\Http\Rules\PasswordRule;
use Modules\Shared\Http\Requests\ApiFormRequest;

class StoreStaffMemberRequest extends ApiFormRequest
{
    use NormalizesEmailInput;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', new EmailRule, 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', new PasswordRule],
            'role' => ['required', Rule::enum(UserRole::class)],
        ];
    }

    public function toDto(): CreateStaffMemberDTO
    {
        return new CreateStaffMemberDTO(
            name: $this->validated('name'),
            email: new Email($this->validated('email')),
            password: new Password($this->validated('password')),
            role: UserRole::from($this->validated('role')),
        );
    }
}
