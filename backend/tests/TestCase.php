<?php

namespace Tests;

use App\Modules\Identity\Models\CustomerAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Signs in on the guard of the account's area: a CustomerAccount on `customer`, a staff
     * User on `staff`. Pass $guard to sign in on the other one on purpose.
     *
     * @param  string|null  $guard
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        return parent::actingAs($user, $guard ?? ($user instanceof CustomerAccount ? 'customer' : 'staff'));
    }
}
