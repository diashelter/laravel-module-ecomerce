<?php

use App\Modules\Identity\Policies\UserPolicy;

it('lets admins edit customers', function () {
    expect((new UserPolicy)->update(admin(), customer()))->toBeTrue();
});

it('never lets administrators be edited through the customers screen', function () {
    expect((new UserPolicy)->update(admin(), admin()))->toBeFalse();
});
