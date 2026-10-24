<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/*
 * Services change state; queries only read it. A page can then call any query
 * without the risk of it writing, and a query can be tested on its own.
 */
class QueriesAreReadOnlyTest extends TestCase
{
    use ReadsImports;

    /** @var array<string, string> what a query must not do, as a pattern, keyed by how to describe it */
    private const WRITES = [
        'save a model' => '/->save\(/',
        'create a model' => '/->create\(/',
        'update a model' => '/->update\(/',
        'delete a model' => '/->delete\(/',
        'open a transaction' => '/DB::transaction\(/',
    ];

    public function test_queries_do_not_use_services(): void
    {
        $violations = [];

        foreach ($this->importsUnder('app/Queries') as $file => $imports) {
            foreach ($imports as $import) {
                if (! str_starts_with($import, 'App\Services\\')) {
                    continue;
                }

                $violations[] = "{$file} uses {$import}";
            }
        }

        $this->assertSame([], $violations, implode("\n", [
            'A query uses a service, and services change state:',
            ...$violations,
            'Have the controller call the service, or move what both need into app/Support.',
        ]));
    }

    public function test_queries_do_not_write(): void
    {
        $violations = [];

        foreach ($this->codeUnder('app/Queries') as $file => $code) {
            foreach (self::WRITES as $write => $pattern) {
                if (! preg_match($pattern, $code)) {
                    continue;
                }

                $violations[] = "{$file} would {$write}";
            }
        }

        $this->assertSame([], $violations, implode("\n", [
            'A query writes:',
            ...$violations,
            'Move the write into a service in app/Services and call it from the controller.',
        ]));
    }
}
