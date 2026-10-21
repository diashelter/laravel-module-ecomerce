<?php

use Modules\Identity\Persistence\Models\CustomerAccount;
use Modules\Identity\Persistence\Models\User;

function customer(array $attributes = []): CustomerAccount
{
    return CustomerAccount::factory()->create($attributes);
}

function admin(array $attributes = []): User
{
    return User::factory()->admin()->create($attributes);
}

function support(array $attributes = []): User
{
    return User::factory()->support()->create($attributes);
}
