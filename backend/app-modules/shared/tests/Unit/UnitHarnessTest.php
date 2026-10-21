<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
 * The tests/Pest.php of the root extends the TestCase to the Unit folder of every module, without the
 * database, the same way it does to tests/Unit.
 */
it('runs a module unit test without the database', function () {
    expect($this)->toBeInstanceOf(TestCase::class)
        ->and(class_uses_recursive($this))->not->toHaveKey(RefreshDatabase::class);
});
