<?php

declare(strict_types=1);

namespace Tests\Feature\Services\Clients;

use App\Enums\Client\ClientType;
use App\Enums\Client\PopiaConsentMethod;
use App\Models\Client;
use App\Services\Clients\RecordPopiaConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecordPopiaConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_a_granted_consent(): void
    {
        $this->freezeTime();
        $client = $this->client();

        $consent = $this->record($client, granted: true);

        $this->assertTrue($consent->granted);
        $this->assertSame($this->method(), $consent->method);
        $this->assertSame(now()->toDateTimeString(), $consent->granted_at->toDateTimeString());
        $this->assertNull($consent->withdrawn_at);
        $this->assertTrue($client->hasGrantedPopiaConsent());
    }

    public function test_it_uses_the_date_the_client_actually_consented(): void
    {
        $signedOn = Carbon::parse('2026-09-01 10:30:00');

        $consent = $this->record($this->client(), granted: true, at: $signedOn);

        $this->assertTrue($consent->granted_at->equalTo($signedOn));
    }

    public function test_granting_twice_does_not_stack_duplicate_rows(): void
    {
        $client = $this->client();

        $first = $this->record($client, granted: true);
        $second = $this->record($client, granted: true);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, $client->popiaConsents()->count());
    }

    public function test_it_records_an_explicit_refusal(): void
    {
        $client = $this->client();

        $consent = $this->record($client, granted: false);

        $this->assertFalse($consent->granted);
        $this->assertNull($consent->granted_at);
        $this->assertFalse($client->hasGrantedPopiaConsent());
    }

    public function test_it_withdraws_an_active_consent(): void
    {
        $client = $this->client();
        $granted = $this->record($client, granted: true);

        $withdrawn = $this->record($client, granted: false);

        $this->assertTrue($withdrawn->is($granted));
        $this->assertNotNull($withdrawn->fresh()->withdrawn_at);
        $this->assertFalse($client->hasGrantedPopiaConsent());
        $this->assertSame(1, $client->popiaConsents()->count());
    }

    public function test_consenting_again_after_a_withdrawal_keeps_the_history(): void
    {
        $client = $this->client();
        $first = $this->record($client, granted: true);
        $this->record($client, granted: false);

        $second = $this->record($client, granted: true);

        $this->assertFalse($first->is($second));
        $this->assertNotNull($first->fresh()->withdrawn_at);
        $this->assertTrue($client->hasGrantedPopiaConsent());
        $this->assertSame(2, $client->popiaConsents()->count());
    }

    public function test_it_refreshes_the_clients_loaded_consent(): void
    {
        $client = $this->client();
        $this->assertFalse($client->hasGrantedPopiaConsent());   // caches the empty relation

        $this->record($client, granted: true);

        $this->assertTrue($client->hasGrantedPopiaConsent());
    }

    public function test_it_attaches_a_signed_mandate_document(): void
    {
        Storage::fake(config('media-library.disk_name'));
        $client = $this->client();

        $consent = $this->record(
            $client,
            granted: true,
            mandate: UploadedFile::fake()->create('mandate.pdf', 20, 'application/pdf'),
        );

        $this->assertNotNull($consent->mandate());
        $this->assertSame('mandate.pdf', $consent->mandate()->file_name);
    }

    public function test_the_mandate_is_optional(): void
    {
        $consent = $this->record($this->client(), granted: true);

        $this->assertNull($consent->mandate());
    }

    private function record(Client $client, bool $granted, ?Carbon $at = null, UploadedFile|string|null $mandate = null)
    {
        return app(RecordPopiaConsent::class)->record($client, $granted, $this->method(), $at, $mandate);
    }

    private function method(): PopiaConsentMethod
    {
        return PopiaConsentMethod::cases()[0];
    }

    private function client(): Client
    {
        return Client::create([
            'type' => ClientType::Individual,
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'id_number' => '9001015800088',
        ]);
    }
}
