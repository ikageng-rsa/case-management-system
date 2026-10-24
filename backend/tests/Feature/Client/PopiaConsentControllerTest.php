<?php

declare(strict_types=1);

namespace Tests\Feature\Client;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\PopiaConsentMethod;
use App\Models\Client\Client;
use App\Models\Client\PopiaConsent;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class PopiaConsentControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
        Storage::fake(config('media-library.disk_name'));
    }

    protected function userWithRole(RoleEnum $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    protected function mandate(): UploadedFile
    {
        return UploadedFile::fake()->create('mandate.pdf', 20, 'application/pdf');
    }

    public function test_consent_is_recorded_with_its_method_date_and_signed_mandate(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.consent.store', $client), [
                'method' => 'signed_mandate',
                'consented_on' => now()->subDays(2)->toDateString(),
                'mandate' => $this->mandate(),
            ])
            ->assertRedirect(route('client.show', $client))
            ->assertSessionHasNoErrors();

        $consent = $client->popiaConsent()->firstOrFail();

        $this->assertTrue($consent->isActive());
        $this->assertSame(PopiaConsentMethod::Signed, $consent->method);
        $this->assertTrue($consent->granted_at->isSameDay(now()->subDays(2)));
        $this->assertSame('mandate.pdf', $consent->mandate()->file_name);
    }

    public function test_a_signed_mandate_is_required_only_for_that_method(): void
    {
        $client = Client::factory()->create();
        $secretary = $this->userWithRole(RoleEnum::Secretary);

        $this->actingAs($secretary)
            ->post(route('client.consent.store', $client), ['method' => 'signed_mandate'])
            ->assertSessionHasErrorsIn('popia', 'mandate');

        $this->assertCount(0, $client->popiaConsents);

        $this->actingAs($secretary)
            ->post(route('client.consent.store', $client), ['method' => 'verbal_witnessed'])
            ->assertSessionHasNoErrors();

        $this->assertNull($client->popiaConsent()->firstOrFail()->mandate());
    }

    public function test_the_date_cannot_be_in_the_future_and_the_file_must_be_a_pdf_or_image(): void
    {
        $client = Client::factory()->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.consent.store', $client), [
                'method' => 'signed_mandate',
                'consented_on' => now()->addDay()->toDateString(),
                'mandate' => UploadedFile::fake()->create('mandate.exe', 5),
            ])
            ->assertSessionHasErrorsIn('popia', ['consented_on', 'mandate']);
    }

    public function test_consent_cannot_be_recorded_over_an_active_one(): void
    {
        $client = Client::factory()->create();
        PopiaConsent::factory()->for($client)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.consent.store', $client), ['method' => 'verbal_witnessed'])
            ->assertSessionHasErrorsIn('popia', 'method');

        $this->assertCount(1, $client->fresh()->popiaConsents);
    }

    public function test_a_consent_is_withdrawn_and_stays_withdrawn(): void
    {
        $client = Client::factory()->create();
        PopiaConsent::factory()->for($client)->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)
            ->post(route('client.consent.withdraw', $client))
            ->assertRedirect(route('client.show', $client));

        $consent = $client->popiaConsent()->firstOrFail();

        $this->assertFalse($consent->isActive());
        $this->assertNotNull($consent->withdrawn_at);

        // There is nothing left to withdraw, and the record is not switched back on.
        $this->actingAs($director)
            ->post(route('client.consent.withdraw', $client))
            ->assertSessionHasErrorsIn('popia', 'consent');

        $this->assertFalse($consent->fresh()->isActive());
        $this->assertCount(1, $client->fresh()->popiaConsents);
    }

    public function test_consenting_again_after_a_withdrawal_is_a_new_record_and_the_history_is_kept(): void
    {
        $client = Client::factory()->create();
        $withdrawn = PopiaConsent::factory()->for($client)->withdrawn()->create(['granted_at' => now()->subYear()]);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->post(route('client.consent.store', $client), ['method' => 'verbal_witnessed'])
            ->assertSessionHasNoErrors();

        $this->assertCount(2, $client->fresh()->popiaConsents);
        $this->assertTrue($client->popiaConsent()->firstOrFail()->isActive());
        $this->assertFalse($withdrawn->fresh()->isActive(), 'the old consent stays withdrawn');
    }

    public function test_recording_and_withdrawing_follow_their_own_permissions(): void
    {
        $client = Client::factory()->create();

        // Secretaries may record consent but not withdraw it.
        $secretary = $this->userWithRole(RoleEnum::Secretary);
        PopiaConsent::factory()->for($client)->create();
        $this->actingAs($secretary)->post(route('client.consent.withdraw', $client))->assertForbidden();
        $this->assertTrue($client->popiaConsent()->firstOrFail()->isActive());

        // Candidate attorneys may do neither.
        $candidate = $this->userWithRole(RoleEnum::CandidateAttorney);
        $other = Client::factory()->create();
        $this->actingAs($candidate)->post(route('client.consent.store', $other), ['method' => 'verbal_witnessed'])->assertForbidden();
    }

    public function test_changes_are_audited_once_by_the_model(): void
    {
        $client = Client::factory()->create();
        PopiaConsent::factory()->for($client)->create();
        $consent = $client->popiaConsent()->firstOrFail();
        Activity::query()->delete();

        $this->actingAs($this->userWithRole(RoleEnum::Director))
            ->post(route('client.consent.withdraw', $client));

        $this->assertSame(1, Activity::forSubject($consent)->where('event', 'updated')->count());
    }

    public function test_the_card_shows_the_state_and_what_may_be_done_about_it(): void
    {
        $client = Client::factory()->create();
        $director = $this->userWithRole(RoleEnum::Director);

        $this->actingAs($director)->get(route('client.show', $client))
            ->assertSee('No consent decision recorded.')
            ->assertDontSee('Record new consent');

        PopiaConsent::factory()->for($client)->withdrawn()->create();

        $this->actingAs($director)->get(route('client.show', $client))
            ->assertSee('Withdrawn')
            ->assertSee('Record new consent')
            ->assertSee('Withdrawn consent cannot be switched back on');
    }

    public function test_the_withdraw_form_is_only_rendered_for_an_active_consent_and_a_user_who_may_withdraw(): void
    {
        $client = Client::factory()->create();
        PopiaConsent::factory()->for($client)->create();

        $this->actingAs($this->userWithRole(RoleEnum::Director))->get(route('client.show', $client))
            ->assertSee(route('client.consent.withdraw', $client), false);

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))->get(route('client.show', $client))
            ->assertDontSee(route('client.consent.withdraw', $client), false);
    }

    public function test_the_signed_mandate_can_be_downloaded_and_the_download_is_recorded(): void
    {
        $client = Client::factory()->create();
        $consent = PopiaConsent::factory()->for($client)->create();
        $consent->addMandate($this->mandate());

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.consent.mandate', [$client, $consent]))
            ->assertOk()
            ->assertDownload('mandate.pdf');

        $this->assertDatabaseHas('activity_log', ['event' => 'downloaded', 'subject_id' => (string) $consent->mandate()->getKey()]);
    }

    public function test_a_mandate_cannot_be_reached_through_another_clients_url_or_without_permission(): void
    {
        $client = Client::factory()->create();
        $consent = PopiaConsent::factory()->create();
        $consent->addMandate($this->mandate());

        $this->actingAs($this->userWithRole(RoleEnum::Secretary))
            ->get(route('client.consent.mandate', [$client, $consent]))
            ->assertNotFound();

        $mine = PopiaConsent::factory()->for($client)->create();
        $mine->addMandate($this->mandate());

        $this->actingAs($this->userWithRole(RoleEnum::Manager))
            ->get(route('client.consent.mandate', [$client, $mine]))
            ->assertForbidden();
    }
}
