<?php

use Modules\Ordering\Core\Services\OrderCodeGenerator;

it('draws 8 symbols of the order code alphabet', function () {
    $generator = new OrderCodeGenerator;
    $codes = array_map(fn () => $generator->generate(), range(1, 1000));

    foreach ($codes as $code) {
        expect($code)->toMatch('/^[ABCDEFGHJKMNPQRSTUVWXYZ23456789]{8}$/');
    }

    expect(count(array_unique($codes)))->toBeGreaterThanOrEqual(990);
});
