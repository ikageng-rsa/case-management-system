<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

/*
 * Each folder under app/Services is one area of the domain. A service may use
 * another area's services only when that link is listed here, so a new
 * dependency between areas is a decision made in review, not an accident.
 */
class ServiceBoundariesTest extends TestCase
{
    use ReadsImports;

    /**
     * The areas each area may use, with the reason for each link.
     *
     * @var array<string, array<string, string>>
     */
    private const ALLOWED = [
        'ActivityTypes' => [
            'Reference' => 'Activity types are reference data, so they share its locked and system-record rules.',
        ],
        'MatterTypes' => [
            'Reference' => 'Matter types are reference data, so they share its locked rule.',
        ],
        'Matters' => [
            'Notifications' => 'Assigning or unassigning someone on a matter tells them.',
        ],
        'Users' => [
            'Clients' => 'A user\'s mobile numbers are normalised the same way a client\'s contacts are.',
            'Matters' => 'Deactivating a user hands their matters to someone else.',
            'Notifications' => 'Deactivating a user tells each successor about the diary entries handed to them.',
            'Roles' => 'Changing roles or deactivating a user must leave someone able to administer.',
        ],
    ];

    public function test_services_use_other_areas_only_through_an_allowed_link(): void
    {
        $undeclared = [];

        foreach ($this->crossAreaImports() as [$file, $area, $import, $used]) {
            if (isset(self::ALLOWED[$area][$used])) {
                continue;
            }

            $undeclared[] = "{$file} uses {$import}";
        }

        $this->assertSame([], $undeclared, implode("\n", [
            'A service uses another area without that link being allowed:',
            ...$undeclared,
            'If the dependency is intended, add it to '.self::class.'::ALLOWED with the reason.',
        ]));
    }

    public function test_every_allowed_link_is_still_used(): void
    {
        $used = [];

        foreach ($this->crossAreaImports() as [, $area, , $usedArea]) {
            $used[$area][$usedArea] = true;
        }

        $stale = [];

        foreach (self::ALLOWED as $area => $links) {
            foreach (array_keys($links) as $linked) {
                if (isset($used[$area][$linked])) {
                    continue;
                }

                $stale[] = "{$area} -> {$linked}";
            }
        }

        $this->assertSame([], $stale, implode("\n", [
            'These allowed links are no longer used:',
            ...$stale,
            'Remove them from '.self::class.'::ALLOWED.',
        ]));
    }

    /** @return list<array{string, string, string, string}> file, its area, the import and the area it imports from */
    private function crossAreaImports(): array
    {
        $links = [];

        foreach ($this->importsUnder('app/Services') as $file => $imports) {
            $area = explode('/', $file)[2];

            foreach ($imports as $import) {
                if (! preg_match('/^App\\\\Services\\\\([^\\\\]+)\\\\/', $import, $match)) {
                    continue;
                }

                if ($match[1] === $area) {
                    continue;
                }

                $links[] = [$file, $area, $import, $match[1]];
            }
        }

        return $links;
    }
}
