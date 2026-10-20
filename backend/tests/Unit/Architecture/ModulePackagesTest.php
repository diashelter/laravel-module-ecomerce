<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use Illuminate\Support\Str;
use InterNACHI\Modular\Support\ModuleRegistry;
use Tests\Unit\Architecture\Support\ModuleLayout;

/*
 * The modules are packages in app-modules, discovered by internachi/modular (see .design/modular-layout.md).
 */

it('lists exactly the eleven module packages', function () {
    $modules = app(ModuleRegistry::class)->modules();

    expect($modules->keys()->sort()->values()->all())
        ->toBe(['backoffice', 'catalog', 'customers', 'fulfillment', 'identity', 'integration', 'inventory', 'messaging', 'ordering', 'payment', 'shared']);

    foreach ($modules as $name => $module) {
        expect($module->namespace())->toBe('Modules\\'.ucfirst($name).'\\');
    }
});

it('keeps no App.Modules reference in the project', function () {
    $project = realpath(base_path('..'));
    $needle = 'App'.'\\Modules';
    $found = [];

    expect(is_dir(base_path('app/Modules')))->toBeFalse();

    // The api container mounts backend/ only; load-tests/ and .github/ are checked from the host (proofs/c02).
    foreach (array_filter(['backend', 'load-tests', '.github'], fn (string $directory) => is_dir("{$project}/{$directory}")) as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator("{$project}/{$directory}", FilesystemIterator::SKIP_DOTS),
            fn (SplFileInfo $file) => ! in_array($file->getFilename(), ['vendor', 'node_modules', 'storage', 'results', 'cache'], true),
        ));

        foreach ($iterator as $file) {
            if (! in_array($file->getExtension(), ['php', 'json', 'xml', 'yml', 'yaml'], true)) {
                continue;
            }

            $text = (string) file_get_contents($file->getPathname());

            if (str_contains($text, $needle) || str_contains($text, str_replace('\\', '\\\\', $needle))) {
                $found[] = str_replace($project.'/', '', $file->getPathname());
            }
        }
    }

    expect($found)->toBe([]);
});

it('registers no module provider in bootstrap providers', function () {
    expect(require base_path('bootstrap/providers.php'))->toBe([AppServiceProvider::class, HorizonServiceProvider::class]);
});

it('keeps every class of a module in an allowed src folder', function (string $module) {
    $src = ModuleLayout::srcOf($module);

    expect(is_dir($src))->toBeTrue()
        ->and(ModuleLayout::foldersOutside($src, ModuleLayout::allowedFolders($module)))->toBe([]);
})->with(ModuleLayout::modules());

it('declares the module library and the path repository', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['require']['internachi/modular'])->toBe('^3.0');

    $paths = array_values(array_filter($composer['repositories'], fn (array $repository) => $repository['type'] === 'path'));

    expect($paths)->toHaveCount(1)
        ->and($paths[0]['url'])->toBe('app-modules/*')
        ->and($paths[0]['options']['symlink'])->toBeTrue();

    foreach (ModuleLayout::modules() as $module) {
        expect($composer['require']['modules/'.Str::kebab($module)])->toBe('*');
    }
});

it('gives every module the manifest of the design', function (string $module) {
    $manifest = json_decode((string) file_get_contents(dirname(ModuleLayout::srcOf($module)).'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $expected = ["Modules\\{$module}\\" => 'src/'];

    // The tests of a module are autoloaded by the module itself (Composer ignores the autoload-dev of a dependency).
    if (is_dir(dirname(ModuleLayout::srcOf($module)).'/tests')) {
        $expected["Modules\\{$module}\\Tests\\"] = 'tests/';
    }

    if (is_dir(dirname(ModuleLayout::srcOf($module)).'/database/factories')) {
        $expected["Modules\\{$module}\\Database\\Factories\\"] = 'database/factories/';
    }

    expect($manifest['name'])->toBe('modules/'.Str::kebab($module))
        ->and($manifest['autoload']['psr-4'])->toBe($expected)
        ->and($manifest['extra']['laravel']['providers'])->toBe(["Modules\\{$module}\\Providers\\{$module}ServiceProvider"]);
})->with(ModuleLayout::modules());

it('maps no module test namespace in the root composer', function () {
    $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);
    $namespaces = [
        ...array_keys($composer['autoload']['psr-4'] ?? []),
        ...array_keys($composer['autoload-dev']['psr-4'] ?? []),
    ];

    expect(array_filter($namespaces, fn (string $namespace) => str_starts_with($namespace, 'Modules\\')))->toBe([]);
});

it('classifies the integration package as composition', function () {
    expect(ModuleLayout::COMPOSITION)->toBe(['Integration'])
        ->and(ModuleLayout::BUSINESS)->not->toContain('Integration')
        ->and(ModuleLayout::INFRASTRUCTURE)->not->toContain('Integration')
        ->and(ModuleLayout::modules())->toContain('Integration');
});
