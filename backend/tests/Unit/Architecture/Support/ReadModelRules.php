<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture\Support;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Who may reach the read model of the storefront (AC 25 of the storefront read model): the MongoDB connection and the
 * two collections belong to the catalog, and inside it only the consumers of the projector, the console commands, the
 * persistence layer and the provider reach them; the writes come from the consumers and from the command that empties
 * them. Works on a directory of module packages (app-modules, or a fixture with the same shape).
 */
final class ReadModelRules
{
    /** What names the read model outside the catalog. */
    private const NAMES = ['catalog_products', 'catalog_categories', "'mongodb'", 'MongoDB\\', 'ReadModelStore', 'ProductDocumentRepository', 'CategoryDocumentRepository'];

    /** The folders of the catalog that may reach it. */
    private const REACH = ['Core/Consumers/', 'Console/', 'Persistence/', 'Providers/'];

    /** The folders of the catalog that may write into it (the persistence classes themselves are the writes). */
    private const WRITE = ['Core/Consumers/', 'Console/', 'Persistence/'];

    /** The calls that write into the read model, through the repositories or the collections. */
    private const WRITES = ['->replaceProductPart(', '->replaceStockPart(', '->tombstone(', '->replace(', '->updateOne(', '->insertOne(', '->deleteMany(', '->drop(', '->createIndex('];

    /** @return list<string> one line per violation: the file and what it does */
    public static function violations(string $modules): array
    {
        $violations = [];

        foreach (self::phpFiles($modules) as $file) {
            $relative = substr($file, strlen($modules) + 1);
            [$module, , $inside] = array_pad(explode('/', $relative, 3), 3, '');
            $code = (string) file_get_contents($file);
            $names = array_values(array_filter(self::NAMES, fn (string $name) => str_contains($code, $name)));

            if ($names === []) {
                continue;
            }

            if ($module !== 'catalog') {
                $violations[] = "{$relative} names {$names[0]} outside the catalog";

                continue;
            }

            $inside = substr($relative, strlen('catalog/src/'));

            if (! self::within($inside, self::REACH)) {
                $violations[] = "{$relative} reaches the read model from outside the consumers, the console, the persistence and the provider";
            }

            foreach (self::WRITES as $write) {
                if (str_contains($code, $write) && ! self::within($inside, self::WRITE)) {
                    $violations[] = "{$relative} writes into the read model ({$write})";
                }
            }
        }

        return $violations;
    }

    /** @param  list<string>  $folders */
    private static function within(string $path, array $folders): bool
    {
        foreach ($folders as $folder) {
            if (str_starts_with($path, $folder)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function phpFiles(string $modules): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modules, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php' && str_contains($file->getPathname(), '/src/')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
