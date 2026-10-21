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
 * The isolation of the modules (see .design/module-isolation.md): the tests of a module use only that module,
 * Shared and Messaging; an isolated module names no other business module and only the integration package
 * names it; a module's inbound events (the classes it reads another module's events with) stay with its consumers.
 */
it('keeps the tests of every module inside the module', function (string $module) {
    expect(ModuleLayout::testsViolations(ModuleLayout::packageOf($module).'/tests', $module))->toBe([]);
})->with(ModuleLayout::modules());

it('isolates the payment module', function () {
    expect(ModuleLayout::isolationViolations(base_path('app-modules'), 'Payment'))->toBe([]);
});

it('lets only the integration package name an isolated module', function (string $module) {
    expect(ModuleLayout::isolationViolations(base_path('app-modules'), $module))->toBe([]);
})->with(ModuleLayout::ISOLATED);

it('fails on a deliberate isolation violation', function (string $fixture, bool $violates) {
    expect(ModuleLayout::isolationViolations(__DIR__.'/Fixtures/Isolation/'.$fixture, 'Payment') !== [])->toBe($violates);
})->with([
    'payment naming ordering' => ['payment-names-ordering', true],
    'ordering naming payment' => ['ordering-names-payment', true],
    'the integration package naming both' => ['integration-names-both', false],
]);

it('keeps the inbound events inside the consumers and the provider', function () {
    expect(ModuleLayout::inboundEventViolations(base_path('app-modules')))->toBe([]);
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
    'a module test naming another business module' => ['module-test-uses-business', fn (string $src) => ModuleLayout::testsViolations(dirname($src).'/tests', 'Payment')],
    'a module test naming the composition suite' => ['module-test-uses-composition', fn (string $src) => ModuleLayout::testsViolations(dirname($src).'/tests', 'Messaging')],
    'a module test calling a helper of another business module' => ['module-test-calls-business-helper', fn (string $src) => ModuleLayout::testsViolations(dirname($src).'/tests', 'Payment')],
    'a module test calling a helper of the composition suite' => ['module-test-calls-composition-helper', fn (string $src) => ModuleLayout::testsViolations(dirname($src).'/tests', 'Payment')],
    'a use case naming an inbound event' => ['inbound-event-outside-consumers', fn (string $src) => ModuleLayout::inboundEventViolations(dirname($src).'/packages')],
]);
