<?php

use Modules\Identity\Core\DTOs\UpdateUserProfileDTO;
use Modules\Identity\Core\ValueObjects\Email;
use Modules\Identity\Core\ValueObjects\Password;

it('keeps the password out of the changes when none is sent', function () {
    $changes = (new UpdateUserProfileDTO('Novo Nome', new Email('novo@example.com')))->toArray();

    expect($changes)->toBe(['name' => 'Novo Nome', 'email' => 'novo@example.com']);
});

it('includes the password when a new one is sent', function () {
    $changes = (new UpdateUserProfileDTO('Nome', new Email('nome@example.com'), new Password('new-secret-password')))->toArray();

    expect($changes)->toBe(['name' => 'Nome', 'email' => 'nome@example.com', 'password' => 'new-secret-password']);
});
