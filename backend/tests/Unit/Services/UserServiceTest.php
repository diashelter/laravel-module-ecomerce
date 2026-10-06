<?php

use App\Modules\Identity\DTOs\UpdateUserProfileDTO;
use App\Modules\Identity\Services\UserService;

it('keeps the password out of the changes when none is sent', function (?string $password) {
    $changes = (new UserService)->profileChanges(new UpdateUserProfileDTO('Novo Nome', 'novo@example.com', $password));

    expect($changes)->toBe(['name' => 'Novo Nome', 'email' => 'novo@example.com']);
})->with(['null' => null, 'empty string' => '']);

it('includes the password when a new one is sent', function () {
    $changes = (new UserService)->profileChanges(new UpdateUserProfileDTO('Nome', 'nome@example.com', 'new-secret-password'));

    expect($changes)->toBe(['name' => 'Nome', 'email' => 'nome@example.com', 'password' => 'new-secret-password']);
});
