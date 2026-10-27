<?php

/*
 * RefreshDatabase rolls PostgreSQL back; MongoDB is emptied by the beforeEach of tests/Pest.php. The two cases run in
 * this order: the first leaves documents behind, the second finds both collections empty.
 */

it('starts each test with an empty read model: the first one leaves documents behind', function () {
    project(productEvent('0199f0a0-0000-7000-8000-0000000000a1', 1));
    project(categoryEvent('0199f0a0-0000-7000-8000-0000000000c1', 1));

    expect(readModel()->products()->countDocuments([]))->toBe(1)
        ->and(readModel()->categories()->countDocuments([]))->toBe(1);
});

it('starts each test with an empty read model: the next one finds nothing', function () {
    expect(readModel()->products()->countDocuments([]))->toBe(0)
        ->and(readModel()->categories()->countDocuments([]))->toBe(0);
});
