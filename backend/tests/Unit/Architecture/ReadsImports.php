<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/*
 * Reads the PHP files under a folder of app/, so the architecture tests can
 * check what each layer depends on and does without booting the application.
 */
trait ReadsImports
{
    /** @return array<string, list<string>> imports keyed by file path relative to the backend root */
    protected function importsUnder(string $folder): array
    {
        return array_map(function (string $source): array {
            preg_match_all('/^use\s+([A-Za-z0-9_\\\\]+)/m', $source, $matches);

            return $matches[1];
        }, $this->sourcesUnder($folder));
    }

    /** @return array<string, string> code with its comments left out, keyed by file path relative to the backend root */
    protected function codeUnder(string $folder): array
    {
        return array_map(function (string $source): string {
            $code = '';

            foreach (token_get_all($source) as $token) {
                if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }

                $code .= is_array($token) ? $token[1] : $token;
            }

            return $code;
        }, $this->sourcesUnder($folder));
    }

    /** @return array<string, string> */
    private function sourcesUnder(string $folder): array
    {
        $root = dirname(__DIR__, 3);
        $sources = [];

        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/{$folder}", RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $sources[substr($file->getPathname(), strlen($root) + 1)] = (string) file_get_contents($file->getPathname());
        }

        ksort($sources);

        return $sources;
    }
}
