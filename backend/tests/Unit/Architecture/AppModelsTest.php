<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/*
 * Some packages build their models from config, and the app's own models
 * extend theirs to add what the firm needs, such as naming someone who has
 * since been deactivated. Querying the package's class directly skips that,
 * so outside the app's own model the package's class is never used.
 */
class AppModelsTest extends TestCase
{
    use ReadsImports;

    /** @var array<string, string> the package's model => the app's model that extends it */
    private const OWN_MODELS = [
        'Spatie\Activitylog\Models\Activity' => 'App\Models\Audit\Activity',
        'Spatie\MediaLibrary\MediaCollections\Models\Media' => 'App\Models\Document\Media',
        'Spatie\Permission\Models\Role' => 'App\Models\User\Role',
    ];

    public function test_the_app_uses_its_own_models_rather_than_the_packages_they_extend(): void
    {
        $violations = [];

        foreach ($this->importsUnder('app') as $file => $imports) {
            foreach ($imports as $import) {
                if (! isset(self::OWN_MODELS[$import])) {
                    continue;
                }

                if ($file === $this->pathOf(self::OWN_MODELS[$import])) {
                    continue;
                }

                $violations[] = "{$file} uses {$import}; use ".self::OWN_MODELS[$import].' instead';
            }
        }

        $this->assertSame([], $violations, implode("\n", [
            'The app uses a package model directly instead of its own:',
            ...$violations,
        ]));
    }

    private function pathOf(string $class): string
    {
        return 'app/'.str_replace('\\', '/', substr($class, strlen('App\\'))).'.php';
    }
}
