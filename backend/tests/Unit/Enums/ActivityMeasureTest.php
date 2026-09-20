<?php

namespace Tests\Unit\Enums;

use App\Enums\Narration\ActivityMeasure;
use PHPUnit\Framework\TestCase;

class ActivityMeasureTest extends TestCase
{
    public function test_only_minutes_are_timed(): void
    {
        $this->assertTrue(ActivityMeasure::Minutes->isTimed());
        $this->assertFalse(ActivityMeasure::Page->isTimed());
        $this->assertFalse(ActivityMeasure::Kilometre->isTimed());
        $this->assertFalse(ActivityMeasure::Item->isTimed());
    }
}
