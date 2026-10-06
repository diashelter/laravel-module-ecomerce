<?php

use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Services\UserService;
use App\Modules\Identity\ValueObjects\Email;
use App\Modules\Identity\ValueObjects\Password;

it('keeps the password out of the changes when none is sent', function () {
    $changes = (new UserService)->profileChanges(new UpdateUserProfileDTO('Novo Nome', new Email('novo@example.com')));

    expect($changes)->toBe(['name' => 'Novo Nome', 'email' => 'novo@example.com']);
});

it('includes the password when a new one is sent', function () {
    $changes = (new UserService)->profileChanges(new UpdateUserProfileDTO('Nome', new Email('nome@example.com'), new Password('new-secret-password')));

    expect($changes)->toBe(['name' => 'Nome', 'email' => 'nome@example.com', 'password' => 'new-secret-password']);
});
