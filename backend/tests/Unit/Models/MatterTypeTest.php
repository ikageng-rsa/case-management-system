<?php

namespace Tests\Unit\Models;

use App\Models\Matter;
use App\Models\MatterType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_knows_whether_it_prescribes(): void
    {
        $litigation = MatterType::factory()->roadAccidentFund()->create();
        $other = MatterType::factory()->nonLitigation()->create();

        $this->assertTrue($litigation->prescribes());
        $this->assertSame(36, $litigation->default_prescription_months);
        $this->assertFalse($other->prescribes());
    }

    public function test_it_scopes_to_litigation_types(): void
    {
        MatterType::factory()->roadAccidentFund()->create();
        MatterType::factory()->nonLitigation()->create();

        $this->assertCount(1, MatterType::litigation()->get());
    }

    public function test_it_has_matters(): void
    {
        $type = MatterType::factory()->create();
        Matter::factory()->for($type, 'matterType')->create();

        $this->assertCount(1, $type->matters);
    }

    public function test_it_is_resolved_by_code_in_routes(): void
    {
        $type = MatterType::factory()->roadAccidentFund()->create();

        $this->assertSame('code', $type->getRouteKeyName());
        $this->assertSame('RAF', $type->getRouteKey());
    }

    public function test_it_soft_deletes(): void
    {
        $type = MatterType::factory()->create();

        $type->delete();

        $this->assertSoftDeleted($type);
    }
}
