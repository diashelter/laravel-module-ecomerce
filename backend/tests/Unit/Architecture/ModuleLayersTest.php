<?php

use Tests\Unit\Architecture\Support\ModuleLayout;

/*
 * The layers inside a module and the direction between infrastructure and business modules.
 * Http -> Core -> Persistence: Core and Persistence never name the Http of their own module.
 */

it('keeps Core and Persistence from using Http', function (string $module) {
    expect(ModuleLayout::layerViolations(ModuleLayout::srcOf($module), $module))->toBe([]);
})->with([...ModuleLayout::BUSINESS, 'Messaging']);

it('keeps the infrastructure modules from using business modules', function (string $module) {
    expect(ModuleLayout::infrastructureViolations(ModuleLayout::srcOf($module), $module))->toBe([]);
})->with(ModuleLayout::INFRASTRUCTURE);

it('keeps Shared from using Messaging', function () {
    $violations = array_filter(
        ModuleLayout::infrastructureViolations(ModuleLayout::srcOf('Shared'), 'Shared'),
        fn (string $violation) => str_ends_with($violation, '-> Messaging'),
    );

    expect($violations)->toBe([]);
});

/*
 * Every rule above has a planted violation: the detector must report it, otherwise the rule on the
 * real tree is passing because it looks at nothing.
 */
it('fails on a deliberate violation', function (string $fixture, Closure $detect) {
    expect($detect(__DIR__.'/Fixtures/Layout/'.$fixture.'/src'))->not->toBe([]);
})->with([
    'a folder outside the allowed set' => ['folder-outside', fn (string $src) => ModuleLayout::foldersOutside($src, ModuleLayout::allowedFolders('Ordering'))],
    'Core using Http' => ['core-uses-http', fn (string $src) => ModuleLayout::layerViolations($src, 'Fake')],
    'an infrastructure module using a business module' => ['infrastructure-uses-business', fn (string $src) => ModuleLayout::infrastructureViolations($src, 'Messaging')],
    'Shared using Messaging' => ['shared-uses-messaging', fn (string $src) => ModuleLayout::infrastructureViolations($src, 'Shared')],
]);
