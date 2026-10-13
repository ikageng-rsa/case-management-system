<?php

declare(strict_types=1);

namespace Tests\Feature\Matter;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Matter\Assignment;
use App\Models\Client;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterType;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatterSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function director(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleEnum::Director->value);

        return $user;
    }

    protected function matter(string $reference, int $sequence, int $year, array $overrides = []): Matter
    {
        return Matter::factory()->create($overrides + [
            'reference' => $reference,
            'sequence_number' => $sequence,
            'opened_year' => $year,
            'instructed_at' => now()->setYear($year),
        ]);
    }

    /** @return array<int, string> references found, in order */
    protected function found(User $user, string $term, array $extra = []): array
    {
        return collect($this->actingAs($user)->getJson(route('matter.search', ['q' => $term] + $extra))->assertOk()->json())
            ->pluck('reference')
            ->all();
    }

    protected function seedFirm(): void
    {
        $civil = MatterType::factory()->create(['code' => 'CIV', 'name' => 'Civil litigation']);
        $criminal = MatterType::factory()->create(['code' => 'CRM', 'name' => 'Criminal']);
        $armstrong = Client::factory()->entity()->create(['entity_name' => 'Armstrong Group']);
        $mokoena = Client::factory()->create(['first_name' => 'Thabo', 'last_name' => 'Mokoena']);

        $this->matter('AG/CIV/1/2026', 1, 2026, ['matter_type_id' => $civil->getKey(), 'client_id' => $armstrong->getKey(), 'title' => 'Armstrong v Road Accident Fund']);
        $this->matter('AG/CRM/2/2025', 2, 2025, ['matter_type_id' => $criminal->getKey(), 'client_id' => $armstrong->getKey(), 'title' => 'State v Armstrong director']);
        $this->matter('TM/CIV/12/2026', 12, 2026, ['matter_type_id' => $civil->getKey(), 'client_id' => $mokoena->getKey(), 'title' => 'Mokoena unfair dismissal']);
        $this->matter('TM/CRM/3/2024', 3, 2024, ['matter_type_id' => $criminal->getKey(), 'client_id' => $mokoena->getKey(), 'title' => 'Assault charge']);
    }

    public function test_it_offers_at_most_five_matters(): void
    {
        Matter::factory()->count(8)->create();

        $this->assertCount(5, $this->found($this->director(), ''));
    }

    public function test_a_word_that_only_says_what_is_wanted_lists_everything(): void
    {
        $this->seedFirm();

        $this->assertCount(4, $this->found($this->director(), 'matter'));
        $this->assertCount(4, $this->found($this->director(), 'matters'));
    }

    public function test_a_year_with_the_word_matter_finds_the_matters_of_that_year(): void
    {
        $this->seedFirm();

        $this->assertEqualsCanonicalizing(['AG/CIV/1/2026', 'TM/CIV/12/2026'], $this->found($this->director(), 'matter 2026'));
        $this->assertSame(['AG/CRM/2/2025'], $this->found($this->director(), 'matter 2025'));
    }

    public function test_words_narrow_one_another_in_any_order(): void
    {
        $this->seedFirm();
        $director = $this->director();

        $this->assertEqualsCanonicalizing(['AG/CIV/1/2026', 'TM/CIV/12/2026'], $this->found($director, 'civ 2026'));
        $this->assertSame(['TM/CIV/12/2026'], $this->found($director, '2026 civ mokoena'));
        $this->assertSame(['AG/CIV/1/2026'], $this->found($director, 'armstrong civ'));
    }

    public function test_a_partial_reference_finds_the_matter_however_it_is_written(): void
    {
        $this->seedFirm();
        $director = $this->director();

        $this->assertSame(['TM/CIV/12/2026'], $this->found($director, 'TM/CIV/12'));
        $this->assertSame(['TM/CIV/12/2026'], $this->found($director, 'tm civ 12'));
        $this->assertSame(['AG/CIV/1/2026'], $this->found($director, 'AG/CIV/1/2026'));
    }

    public function test_a_number_is_the_file_number_and_a_zero_padded_one_still_finds_it(): void
    {
        $this->seedFirm();
        $director = $this->director();

        $this->assertSame(['AG/CRM/2/2025'], $this->found($director, 'crm 2'));
        $this->assertSame(['AG/CRM/2/2025'], $this->found($director, 'crm 0002'));
        $this->assertSame(['TM/CIV/12/2026'], $this->found($director, 'civ 12'));
    }

    public function test_it_matches_the_type_the_clients_name_and_the_title(): void
    {
        $this->seedFirm();
        $director = $this->director();

        $this->assertEqualsCanonicalizing(['AG/CRM/2/2025', 'TM/CRM/3/2024'], $this->found($director, 'criminal'));
        $this->assertEqualsCanonicalizing(['AG/CIV/1/2026', 'AG/CRM/2/2025'], $this->found($director, 'armstrong'));
        $this->assertSame(['AG/CIV/1/2026'], $this->found($director, 'road fund'));
        $this->assertSame(['TM/CRM/3/2024'], $this->found($director, 'assault'));
    }

    public function test_a_word_that_matches_nothing_finds_nothing(): void
    {
        $this->seedFirm();

        $this->assertSame([], $this->found($this->director(), 'civ zzzz'));
    }

    public function test_matches_that_start_with_what_was_typed_come_first(): void
    {
        $this->matter('TM/AGR/1/2026', 1, 2026, ['title' => 'Plain']);
        $this->matter('AG/CIV/1/2026', 1, 2026, ['title' => 'Plain']);

        $this->assertSame(['AG/CIV/1/2026', 'TM/AGR/1/2026'], $this->found($this->director(), 'ag'));
    }

    public function test_the_wildcards_of_a_search_are_taken_literally(): void
    {
        $this->seedFirm();

        $this->assertSame([], $this->found($this->director(), '%'));
        $this->assertSame([], $this->found($this->director(), '_'));
    }

    public function test_a_user_only_finds_the_matters_they_may_see(): void
    {
        $this->seedFirm();
        $attorney = User::factory()->create();
        $attorney->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for(Matter::where('reference', 'TM/CIV/12/2026')->first())->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->assertSame(['TM/CIV/12/2026'], $this->found($attorney, 'matter'));
    }

    public function test_the_fileable_scope_offers_only_open_matters_the_user_carries(): void
    {
        $open = $this->matter('AG/CIV/1/2026', 1, 2026);
        $closed = Matter::factory()->closed()->create(['reference' => 'AG/CIV/2/2026', 'sequence_number' => 2, 'opened_year' => 2026]);
        $this->matter('AG/CIV/3/2026', 3, 2026);

        $attorney = User::factory()->create();
        $attorney->assignRole(RoleEnum::Attorney->value);

        foreach ([$open, $closed] as $matter) {
            MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();
        }

        $this->assertSame(['AG/CIV/1/2026'], $this->found($attorney, 'civ', ['scope' => 'fileable']));
        // A director sees every matter in the picker of the matters page, but may only file on the ones they carry.
        $this->assertSame([], $this->found($this->director(), 'civ', ['scope' => 'fileable']));
        $this->assertCount(3, $this->found($this->director(), 'civ'));
    }

    public function test_each_match_carries_what_is_needed_to_tell_it_apart(): void
    {
        $this->seedFirm();

        $match = $this->actingAs($this->director())->getJson(route('matter.search', ['q' => 'road fund']))->json(0);

        $this->assertSame('AG/CIV/1/2026', $match['reference']);
        $this->assertSame('Armstrong v Road Accident Fund', $match['title']);
        $this->assertSame('Armstrong Group', $match['client']);
        $this->assertSame('Civil litigation', $match['type']);
        $this->assertSame(2026, $match['year']);
        $this->assertFalse($match['closed']);
        $this->assertNotEmpty($match['id']);
    }

    public function test_the_search_is_for_signed_in_users(): void
    {
        $this->getJson(route('matter.search', ['q' => 'civ']))->assertUnauthorized();
    }

    public function test_the_upload_sheet_uses_the_picker_and_starts_on_the_matter_being_filtered(): void
    {
        $matter = $this->matter('AG/CIV/1/2026', 1, 2026);
        $attorney = User::factory()->create();
        $attorney->assignRole(RoleEnum::Attorney->value);
        MatterAssignment::factory()->for($matter)->for($attorney)->capacity(Assignment::Responsible)->create();

        $this->actingAs($attorney)
            ->get(route('documents', ['matter' => $matter->getKey()]))
            ->assertOk()
            ->assertSee('matterPicker', false)
            ->assertViewHas('uploadMatter', fn ($selected) => $selected?->is($matter));

        $this->actingAs($attorney)
            ->get(route('documents'))
            ->assertViewHas('uploadMatter', null);
    }
}
