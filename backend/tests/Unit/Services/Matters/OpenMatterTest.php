<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Matters;

use App\Models\Client;
use App\Models\Court;
use App\Models\MatterType;
use App\Services\Matters\OpenMatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenMatterTest extends TestCase
{
    use RefreshDatabase;

    private function service(): OpenMatter
    {
        return app(OpenMatter::class);
    }

    public function test_it_opens_a_matter_with_a_generated_reference(): void
    {
        $client = Client::factory()->create(['first_name' => 'Kgomotso Tsholo', 'last_name' => 'Malla']);
        $type = MatterType::factory()->create(['code' => 'CIV']);

        $matter = $this->service()->open($client, $type, title: 'Malla v Road Accident Fund', court: Court::factory()->create());

        $this->assertStringStartsWith('KTM/CIV/1/', $matter->reference);
        $this->assertTrue($matter->exists);
        $this->assertTrue($matter->client->is($client));
        $this->assertTrue($matter->isOpen());
    }

    public function test_it_computes_prescription_from_the_instruction_date(): void
    {
        $type = MatterType::factory()->create(['code' => 'CIV', 'default_prescription_months' => 36]);

        $matter = $this->service()->open(
            Client::factory()->create(),
            $type,
            instructedAt: now()->parse('2026-01-01'),
        );

        $this->assertSame('2029-01-01', $matter->prescribes_at->toDateString());
    }

    public function test_it_leaves_non_litigation_matters_without_a_court_or_prescription(): void
    {
        $type = MatterType::factory()->nonLitigation()->create();

        $matter = $this->service()->open(Client::factory()->create(), $type, court: Court::factory()->create());

        $this->assertNull($matter->court_id);
        $this->assertNull($matter->prescribes_at);
    }
}
