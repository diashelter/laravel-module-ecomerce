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

    private const LAYERS = ['Contracts', 'Events', 'Core', 'Persistence', 'Http', 'Providers'];

    public static function modules(): array
    {
        return [...self::BUSINESS, ...self::INFRASTRUCTURE];
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

    /** @return list<string> the folders a module may have directly under src */
    public static function allowedFolders(string $module): array
    {
        return match ($module) {
            'Shared' => ['Cache', 'Enums', 'Exceptions', 'Http', 'Providers', 'Repositories', 'Support'],
            'Payment' => [...self::LAYERS, 'Gateways'],
            'Messaging' => [...self::LAYERS, 'Console'],
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
