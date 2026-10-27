<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The layers depend inwards: controllers and requests use services, queries
 * and models; services and queries use models; models use neither. A helper
 * more than one layer needs belongs in app/Support.
 */
class LayeringTest extends TestCase
{
    use ReadsImports;

    #[DataProvider('forbiddenDependencies')]
    public function test_a_layer_does_not_depend_on_the_layer_above_it(string $folder, string $forbidden, string $reason): void
    {
        $violations = [];

        foreach ($this->importsUnder($folder) as $file => $imports) {
            foreach ($imports as $import) {
                if (! str_starts_with($import, "{$forbidden}\\")) {
                    continue;
                }

                $violations[] = "{$file} uses {$import}";
            }
        }

        $this->assertSame([], $violations, implode("\n", [
            "{$folder} must not use {$forbidden}: {$reason}",
            ...$violations,
            'Pass the value in from the caller, or move what both need into app/Support.',
        ]));
    }

    /** @return array<string, array{string, string, string}> */
    public static function forbiddenDependencies(): array
    {
        return [
            'services do not use the http layer' => ['app/Services', 'App\Http', 'they run from controllers, commands and jobs alike.'],
            'queries do not use the http layer' => ['app/Queries', 'App\Http', 'they take typed values, not the request.'],
            'models do not use services' => ['app/Models', 'App\Services', 'services use models, not the other way round.'],
            'models do not use queries' => ['app/Models', 'App\Queries', 'queries use models, not the other way round.'],
        ];
    }
}
