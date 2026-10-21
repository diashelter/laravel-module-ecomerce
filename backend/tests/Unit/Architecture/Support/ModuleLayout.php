<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Support;

use Illuminate\Support\Str;

/**
 * What the architecture tests need to know about the physical layout of app-modules: where a
 * module's src lives, which folders it may have, and which classes its files name.
 */
final class ModuleLayout
{
    /** The business modules: each has its own language and its own layers. */
    public const BUSINESS = ['Backoffice', 'Catalog', 'Customers', 'Fulfillment', 'Identity', 'Inventory', 'Ordering', 'Payment'];

    /** Infrastructure modules: they serve the business modules and never use one. */
    public const INFRASTRUCTURE = ['Shared', 'Messaging'];

    /** The composition package: the only one that names more than one business module, and only by their facades. */
    public const COMPOSITION = ['Integration'];

    /**
     * The business modules already isolated (see .design/module-isolation.md): each names no other business
     * module, and no package but the composition one names it. The list grows slice by slice.
     */
    public const ISOLATED = ['Payment'];

    private const LAYERS = ['Contracts', 'Events', 'Core', 'Persistence', 'Http', 'Providers'];

    public static function modules(): array
    {
        return [...self::BUSINESS, ...self::INFRASTRUCTURE, ...self::COMPOSITION];
    }

    /** The package folder of a module: app-modules/<kebab>. */
    public static function packageOf(string $module): string
    {
        return base_path('app-modules/'.Str::kebab($module));
    }

    public static function srcOf(string $module): string
    {
        return base_path('app-modules/'.Str::kebab($module).'/src');
    }

    /** @return list<string> the files under every module's src that match a glob pattern such as "Events/*.php" */
    public static function filesMatching(string $pattern): array
    {
        return glob(base_path("app-modules/*/src/{$pattern}")) ?: [];
    }

    /** The class a file of app-modules declares, from its path: app-modules/ordering/src/Events/OrderPaid.php -> Modules\Ordering\Events\OrderPaid. */
    public static function classOf(string $file): string
    {
        [$module, $rest] = explode('/src/', substr($file, strlen(base_path('app-modules/'))), 2);

        return 'Modules\\'.Str::studly($module).'\\'.str_replace('/', '\\', substr($rest, 0, -4));
    }

    /**
     * The classes a module's routes file names that are not controllers of that same module: a routes
     * file is the module's own HTTP entry point, so it never reaches another module's Http.
     *
     * @return list<string>
     */
    public static function routeViolations(string $routesFile, string $module): array
    {
        preg_match_all('/Modules\\\\[A-Za-z\\\\]+/', (string) file_get_contents($routesFile), $matches);

        return array_values(array_unique(array_filter(
            $matches[0],
            fn (string $reference) => ! str_starts_with($reference, "Modules\\{$module}\\Http\\Controllers\\"),
        )));
    }

    /** @return list<string> the folders a module may have directly under src */
    public static function allowedFolders(string $module): array
    {
        return match ($module) {
            'Shared' => ['Cache', 'Enums', 'Exceptions', 'Http', 'Providers', 'Repositories', 'Support'],
            'Payment' => [...self::LAYERS, 'Gateways'],
            'Messaging' => [...self::LAYERS, 'Console'],
            // One folder per consumer module, with the adapters of its ports, and the provider that binds them.
            'Integration' => ['Providers', ...self::BUSINESS],
            default => self::LAYERS,
        };
    }

    /** @return list<string> what sits directly under src and is not an allowed folder (loose files included) */
    public static function foldersOutside(string $src, array $allowed): array
    {
        $outside = [];

        foreach (scandir($src) ?: [] as $entry) {
            if (in_array($entry, ['.', '..'], true) || (is_dir("{$src}/{$entry}") && in_array($entry, $allowed, true))) {
                continue;
            }

            $outside[] = $entry;
        }

        return $outside;
    }

    /** @return list<string> files of Core and Persistence that name a class of the same module's Http */
    public static function layerViolations(string $src, string $module): array
    {
        $violations = [];

        foreach (['Core', 'Persistence'] as $layer) {
            foreach (self::phpFilesIn("{$src}/{$layer}") as $file) {
                foreach (self::referencesIn($file) as [$referencedModule, $folder]) {
                    if ($referencedModule === $module && $folder === 'Http') {
                        $violations[] = "{$layer}/".basename($file);
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> files of an infrastructure module that name a business module (or, for Shared, the Messaging) */
    public static function infrastructureViolations(string $src, string $module): array
    {
        $violations = [];

        foreach (self::phpFilesIn($src) as $file) {
            foreach (self::referencesIn($file) as [$referencedModule]) {
                $forbidden = in_array($referencedModule, self::BUSINESS, true)
                    || ($module === 'Shared' && $referencedModule === 'Messaging');

                if ($forbidden) {
                    $violations[] = basename($file).' -> '.$referencedModule;
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * Files of a module's tests that name another business module, the composition package or the support of
     * the composition suite (backend/tests, but its TestCase), or that call a helper declared by one of them:
     * the tests of a module run with only that module, Shared and Messaging installed.
     *
     * @return list<string>
     */
    public static function testsViolations(string $tests, string $module): array
    {
        $violations = [];
        $foreignHelpers = self::foreignHelpersOf($module);

        foreach (self::phpFilesIn($tests) as $file) {
            foreach (self::referencesIn($file) as [$referencedModule]) {
                if ($referencedModule !== $module && in_array($referencedModule, [...self::BUSINESS, ...self::COMPOSITION], true)) {
                    $violations[] = substr($file, strlen($tests) + 1).' -> '.$referencedModule;
                }
            }

            preg_match_all('/(?<![\\\\\w])Tests\\\\{1,2}([A-Z][A-Za-z]*)/', (string) file_get_contents($file), $matches);

            foreach (array_diff($matches[1], ['TestCase']) as $support) {
                $violations[] = substr($file, strlen($tests) + 1).' -> Tests\\'.$support;
            }

            // A plain function call, never a method, a static call, a closure in a variable or a declaration.
            preg_match_all('/(?<![\w$>:])(?<!function )([a-z][A-Za-z0-9_]*)\(/', (string) file_get_contents($file), $calls);

            foreach (array_intersect_key($foreignHelpers, array_flip($calls[1])) as $helper => $owner) {
                $violations[] = substr($file, strlen($tests) + 1)." -> {$helper}() of {$owner}";
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * The test helpers a module's tests may not call, with the owner of each: the ones of tests/Pest.php and
     * of the other business modules and the composition package. tests/Pest.php loads every helper for every
     * test, so a call to one names no class and would pass the rules above.
     *
     * @return array<string, string>
     */
    private static function foreignHelpersOf(string $module): array
    {
        $files = [base_path('tests/Pest.php') => 'the composition suite'];

        foreach (array_diff([...self::BUSINESS, ...self::COMPOSITION], [$module]) as $owner) {
            $files[self::packageOf($owner).'/tests/Helpers.php'] = $owner;
        }

        $helpers = [];

        foreach (array_filter($files, is_file(...), ARRAY_FILTER_USE_KEY) as $file => $owner) {
            preg_match_all('/^function ([A-Za-z_][A-Za-z0-9_]*)\(/m', (string) file_get_contents($file), $declared);

            foreach ($declared[1] as $helper) {
                $helpers[$helper] = $owner;
            }
        }

        return $helpers;
    }

    /**
     * Files that break the isolation of a module: in its own package, a name of another business module; in
     * any other package but the composition one, a name of the module.
     *
     * @return list<string>
     */
    public static function isolationViolations(string $packages, string $module): array
    {
        $violations = [];

        foreach (glob("{$packages}/*", GLOB_ONLYDIR) ?: [] as $package) {
            $owner = Str::studly(basename($package));

            if (in_array($owner, self::COMPOSITION, true)) {
                continue;
            }

            foreach (self::phpFilesIn($package) as $file) {
                foreach (self::referencesIn($file) as [$referencedModule]) {
                    $outgoing = $owner === $module && $referencedModule !== $module && in_array($referencedModule, self::BUSINESS, true);
                    $incoming = $owner !== $module && $referencedModule === $module;

                    if ($outgoing || $incoming) {
                        $violations[] = substr($file, strlen($packages) + 1).' -> '.$referencedModule;
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /**
     * Source files that name a module's inbound events (the classes it reads another module's events with)
     * outside its consumers, its provider and the inbound events themselves: only the producer records an event.
     *
     * @return list<string>
     */
    public static function inboundEventViolations(string $packages): array
    {
        $violations = [];

        foreach (glob("{$packages}/*/src", GLOB_ONLYDIR) ?: [] as $src) {
            foreach (self::phpFilesIn($src) as $file) {
                preg_match_all('/\bModules\\\\{1,2}([A-Z][A-Za-z]*)\\\\{1,2}Core\\\\{1,2}InboundEvents\b/', (string) file_get_contents($file), $matches);

                foreach (array_unique($matches[1]) as $owner) {
                    $ownSrc = "{$packages}/".Str::kebab($owner).'/src/';
                    $allowed = array_filter(
                        ['Core/Consumers/', 'Providers/', 'Core/InboundEvents/'],
                        fn (string $folder) => str_starts_with($file, $ownSrc.$folder),
                    );

                    if ($allowed === []) {
                        $violations[] = substr($file, strlen($packages) + 1).' -> '.$owner;
                    }
                }
            }
        }

        return array_values(array_unique($violations));
    }

    /** @return list<string> */
    private static function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return list<array{string, string}> the [module, first folder] of every Modules\Module\Folder name in a file */
    private static function referencesIn(string $file): array
    {
        $code = preg_replace('/^namespace .*;$/m', '', (string) file_get_contents($file));
        preg_match_all('/\bModules\\\\{1,2}([A-Z][A-Za-z]*)\\\\{1,2}([A-Za-z]+)/', (string) $code, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match) => [$match[1], $match[2]], $matches);
    }
}
