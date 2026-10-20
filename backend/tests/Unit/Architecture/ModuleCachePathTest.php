<?php

use Illuminate\Support\Env;
use InterNACHI\Modular\Support\Cache;

/*
 * The module library keeps the module list in a cache file and has no setting for where it goes. The app
 * binds it again so that the path follows APP_MODULES_CACHE, like the framework's own APP_CONFIG_CACHE: the
 * load stack caches the modules inside its container and never writes bootstrap/cache, which the dev API reads.
 */

function modulesCacheAt(?string $path): Cache
{
    $repository = Env::getRepository();
    $path === null ? $repository->clear('APP_MODULES_CACHE') : $repository->set('APP_MODULES_CACHE', $path);
    app()->forgetInstance(Cache::class);

    return app(Cache::class);
}

it('takes the module cache path from APP_MODULES_CACHE', function () {
    $directory = sys_get_temp_dir().'/modules-cache-'.uniqid();
    $file = $directory.'/app-modules.php';
    $default = base_path('bootstrap/cache/app-modules.php');
    $hadDefault = is_file($default);

    try {
        modulesCacheAt($file)->write(['probe' => true]);

        expect(is_file($file))->toBeTrue()
            ->and(is_file($default))->toBe($hadDefault);
    } finally {
        modulesCacheAt(null);
        @unlink($file);
        @rmdir($directory);
    }
});

it('defaults the module cache path to bootstrap cache', function () {
    $default = base_path('bootstrap/cache/app-modules.php');
    $cache = modulesCacheAt(null);

    $path = (new ReflectionProperty(Cache::class, 'path'))->getValue($cache);

    expect($path)->toBe($default);
});
