<?php

use App\Providers\AppServiceProvider;
use App\Providers\HorizonServiceProvider;
use InterNACHI\Modular\Support\ModuleRegistry;
use Tests\Unit\Architecture\Support\ModuleLayout;

/*
 * The modules are packages in app-modules, discovered by internachi/modular (see .design/modular-layout.md).
 */

it('lists exactly the ten module packages', function () {
    $modules = app(ModuleRegistry::class)->modules();

    expect($modules->keys()->sort()->values()->all())
        ->toBe(['backoffice', 'catalog', 'customers', 'fulfillment', 'identity', 'inventory', 'messaging', 'ordering', 'payment', 'shared']);

    foreach ($modules as $name => $module) {
        expect($module->namespace())->toBe('Modules\\'.ucfirst($name).'\\');
    }
});

it('keeps no App.Modules reference in the project', function () {
    $project = realpath(base_path('..'));
    $needle = 'App'.'\\Modules';
    $found = [];

    expect(is_dir(base_path('app/Modules')))->toBeFalse();

    foreach (['backend', 'load-tests', '.github'] as $directory) {
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
