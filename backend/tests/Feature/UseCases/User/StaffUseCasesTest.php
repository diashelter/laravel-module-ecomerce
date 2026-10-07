<?php

use App\Modules\Identity\DTOs\UpdateStaffMemberDTO;
use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Enums\UserRole;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\UseCases\DeleteStaffMemberUseCase;
use App\Modules\Identity\UseCases\UpdateStaffMemberUseCase;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Shared\Exceptions\BusinessRuleException;

function staffUpdate(User $member, string $name, UserRole $role): UpdateStaffMemberDTO
{
    return new UpdateStaffMemberDTO(new UpdateUserProfileDTO($name, new Email($member->email)), $role);
}

it('decides when a staff update may change the role', function (bool $ownAccount, UserRole $newRole, bool $allowed) {
    $actor = admin();
    $target = $ownAccount ? $actor : support();
    $before = $target->role;

    $run = fn () => app(UpdateStaffMemberUseCase::class)->execute($actor, $target, staffUpdate($target, 'Outro Nome', $newRole));

    if ($allowed) {
        expect($run()->fresh()->role)->toBe($newRole)
            ->and($target->fresh()->name)->toBe('Outro Nome');

        return;
    }

    expect($run)->toThrow(BusinessRuleException::class, 'Você não pode alterar o próprio papel.')
        ->and($target->fresh()->role)->toBe($before)
        ->and($target->fresh()->name)->not->toBe('Outro Nome');
})->with([
    'own account, different role' => [true, UserRole::Support, false],
    'own account, same role' => [true, UserRole::Admin, true],
    'other account, different role' => [false, UserRole::Admin, true],
]);

it('decides when a staff member may be removed', function () {
    $actor = admin();
    $other = support();

    expect(fn () => app(DeleteStaffMemberUseCase::class)->execute($actor, $actor))
        ->toThrow(BusinessRuleException::class, 'Você não pode remover a própria conta.')
        ->and(User::query()->whereKey($actor->id)->exists())->toBeTrue();

    app(DeleteStaffMemberUseCase::class)->execute($actor, $other);

    expect(User::query()->whereKey($other->id)->exists())->toBeFalse();
});
