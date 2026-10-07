<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests\Admin;

use App\Modules\Identity\DTOs\CreateStaffMemberDTO;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesEmailInput;
use App\Modules\Identity\Http\Rules\EmailRule;
use App\Modules\Identity\Http\Rules\PasswordRule;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;
use App\Modules\Shared\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

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
