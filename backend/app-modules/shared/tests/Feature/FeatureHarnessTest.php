<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/*
 * The tests/Pest.php of the root extends the TestCase and RefreshDatabase to the Feature folder of every
 * module, the same way it does to tests/Feature.
 */
it('runs a module feature test with the refreshed database', function () {
    expect($this)->toBeInstanceOf(TestCase::class)
        ->and(class_uses_recursive($this))->toHaveKey(RefreshDatabase::class)
        ->and(Schema::hasTable('migrations'))->toBeTrue();
});
