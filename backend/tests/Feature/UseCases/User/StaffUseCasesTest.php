<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Identity\Core\DTOs\UpdateStaffMemberDTO;
use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\Enums\UserRole;
use Modules\Identity\Core\UseCases\DeleteStaffMemberUseCase;
use Modules\Identity\Core\UseCases\UpdateStaffMemberUseCase;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Persistence\Models\User;
use Modules\Shared\Exceptions\BusinessRuleException;

function staffUpdate(User $member, string $name, UserRole $role): UpdateStaffMemberDTO
{
    return new UpdateStaffMemberDTO(new UpdateUserProfileDTO($name, new Email($member->email)), $role);
}

it('decides when a staff update may change the role', function (bool $ownAccount, UserRole $newRole, bool $allowed) {
    $actor = admin();
    $target = $ownAccount ? $actor : support();
    $before = $target->role;

    $run = fn () => app(UpdateStaffMemberUseCase::class)->execute($actor, $target->id, staffUpdate($target, 'Outro Nome', $newRole));

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

    expect(fn () => app(DeleteStaffMemberUseCase::class)->execute($actor, $actor->id))
        ->toThrow(BusinessRuleException::class, 'Você não pode remover a própria conta.')
        ->and(User::query()->whereKey($actor->id)->exists())->toBeTrue();

    app(DeleteStaffMemberUseCase::class)->execute($actor, $other->id);

    expect(User::query()->whereKey($other->id)->exists())->toBeFalse();
});

it('deletes a staff member by id and refuses an unknown one', function () {
    $actor = admin();
    $other = support();

    app(DeleteStaffMemberUseCase::class)->execute($actor, $other->id);

    expect(User::query()->whereKey($other->id)->exists())->toBeFalse();

    expect(fn () => app(DeleteStaffMemberUseCase::class)->execute($actor, unknownId()))->toThrow(ModelNotFoundException::class)
        ->and(User::query()->whereKey($actor->id)->exists())->toBeTrue();
});

it('refuses to update an unknown staff member', function () {
    $actor = admin();

    expect(fn () => app(UpdateStaffMemberUseCase::class)->execute($actor, unknownId(), new UpdateStaffMemberDTO(new UpdateUserProfileDTO('Nome', new Email('nome@example.com')), UserRole::Support)))
        ->toThrow(ModelNotFoundException::class);
});
