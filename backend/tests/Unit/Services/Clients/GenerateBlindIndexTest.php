<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Clients;

use App\Services\Clients\GenerateBlindIndex;
use Tests\TestCase;

class GenerateBlindIndexTest extends TestCase
{
    public function test_it_hashes_the_same_value_to_the_same_result(): void
    {
        $this->assertSame(
            GenerateBlindIndex::of('9001015800083'),
            GenerateBlindIndex::of('9001015800083'),
        );
    }

    public function test_it_ignores_casing_and_surrounding_whitespace(): void
    {
        $this->assertSame(
            GenerateBlindIndex::of('jane@example.com'),
            GenerateBlindIndex::of('  Jane@Example.COM '),
        );
    }

    public function test_it_hashes_different_values_differently(): void
    {
        $this->assertNotSame(
            GenerateBlindIndex::of('9001015800083'),
            GenerateBlindIndex::of('9001015800084'),
        );
    }

    public function test_it_does_not_store_the_plaintext(): void
    {
        $hash = GenerateBlindIndex::of('9001015800083');

        $this->assertNotSame('9001015800083', $hash);
        $this->assertSame(64, strlen($hash));
    }

    public function test_it_returns_null_for_empty_values(): void
    {
        $this->assertNull(GenerateBlindIndex::of(null));
        $this->assertNull(GenerateBlindIndex::of('   '));
    }
}
