<?php

namespace Tests\Unit;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Document\DocumentKind;
use App\Models\Client;
use App\Models\Matter;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('documents');
    }

    public function test_it_describes_a_created_record_in_words(): void
    {
        $matter = Matter::factory()->create();

        $this->assertSame(
            "System created matter {$matter->reference}",
            Activity::forSubject($matter)->latest('id')->first()->description,
        );
    }

    public function test_it_names_the_person_who_made_the_change(): void
    {
        $user = User::factory()->create(['name' => 'T. Baloyi']);
        $this->actingAs($user);

        $matter = Matter::factory()->create();

        $activity = Activity::forSubject($matter)->latest('id')->first();

        $this->assertSame("T. Baloyi created matter {$matter->reference}", $activity->description);
        $this->assertTrue($user->is($activity->causer));
    }

    public function test_it_records_a_read_as_its_own_entry(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'P. Motaung']));
        $matter = Matter::factory()->create();

        $matter->recordAccess();

        $activity = Activity::forSubject($matter)->where('event', 'read')->first();

        $this->assertSame("P. Motaung read matter {$matter->reference}", $activity->description);
    }

    public function test_it_records_a_document_download_under_its_file_name(): void
    {
        $this->actingAs(User::factory()->create(['name' => 'L. Ngunduza']));
        $matter = Matter::factory()->create();

        $document = $matter->addDocument(
            UploadedFile::fake()->create('bundle.pdf'),
            DocumentKind::Bundle,
        );

        $document->recordAccess('downloaded');

        $this->assertDatabaseHas('activity_log', [
            'event' => 'downloaded',
            'description' => 'L. Ngunduza downloaded document bundle.pdf',
        ]);
    }

    public function test_it_records_the_same_access_once_per_request(): void
    {
        $matter = Matter::factory()->create();

        $matter->recordAccess();
        $matter->recordAccess();
        $matter->recordAccess('viewed');

        $this->assertSame(1, Activity::forSubject($matter)->where('event', 'read')->count());
        $this->assertSame(1, Activity::forSubject($matter)->where('event', 'viewed')->count());
    }

    public function test_it_logs_only_the_attributes_that_changed(): void
    {
        $matter = Matter::factory()->create();

        $matter->update(['title' => 'Nkosi v Road Accident Fund']);

        $activity = Activity::forSubject($matter)->where('event', 'updated')->first();

        $this->assertSame(['title'], array_keys($activity->attribute_changes['attributes']));
    }

    public function test_it_keeps_a_client_id_number_out_of_the_log(): void
    {
        $client = Client::factory()->create(['id_number' => '9001015800086']);

        $client->update(['id_number' => '9001015800087']);

        $log = Activity::forSubject($client)->get()->toJson();

        $this->assertStringNotContainsString('9001015800086', $log);
        $this->assertStringNotContainsString('9001015800087', $log);
    }

    public function test_it_logs_a_deletion(): void
    {
        $matter = Matter::factory()->create();
        $reference = $matter->reference;

        $matter->delete();

        $this->assertDatabaseHas('activity_log', [
            'event' => 'deleted',
            'description' => "System deleted matter {$reference}",
        ]);
    }

    public function test_it_logs_a_role_being_granted_and_revoked(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['name' => 'N. Dlamini']);

        $user->assignRole(RoleEnum::Attorney->value);
        $user->removeRole(RoleEnum::Attorney->value);

        $this->assertDatabaseHas('activity_log', [
            'event' => 'role_granted',
            'description' => 'System granted the attorney role to N. Dlamini',
        ]);
        $this->assertDatabaseHas('activity_log', [
            'event' => 'role_revoked',
            'description' => 'System revoked the attorney role from N. Dlamini',
        ]);
    }
}
