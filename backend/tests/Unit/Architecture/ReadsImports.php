<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/*
 * Reads the class imports of the PHP files under a folder of app/, so the
 * architecture tests can check what each layer depends on without booting
 * the application.
 */
trait ReadsImports
{
    /** @return array<string, list<string>> imports keyed by file path relative to the backend root */
    protected function importsUnder(string $folder): array
    {
        $root = dirname(__DIR__, 3);
        $imports = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$folder}", RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)/m', (string) file_get_contents($file->getPathname()), $matches);

            $imports[substr($file->getPathname(), strlen($root) + 1)] = $matches[1];
        }

        ksort($imports);

        return $imports;
    }
}
