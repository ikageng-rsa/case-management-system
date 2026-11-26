<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Billing\Bill;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);
    }

    protected function userIn(RoleEnum $role): User
    {
        return tap(User::factory()->create())->assignRole($role->value);
    }

    /** @return array<string, list<string>> titles found, by kind */
    protected function found(User $user, string $term): array
    {
        return collect($this->actingAs($user)->getJson(route('search', ['q' => $term]))->assertOk()->json('groups'))
            ->mapWithKeys(fn (array $group) => [$group['label'] => array_column($group['items'], 'title')])
            ->all();
    }

    public function test_it_finds_matters_clients_narrations_and_bills_by_what_was_typed(): void
    {
        $client = Client::factory()->create(['first_name' => 'Nomvula', 'last_name' => 'Zwane']);
        $matter = Matter::factory()->for($client)->create(['title' => 'Zwane v Minister of Police']);
        Narration::factory()->for($matter)->create(['body' => 'Consulted with Mrs Zwane about the arrest.']);
        Bill::factory()->for($matter)->issued('INV/2026/0042')->create(['addressee' => 'Mrs N Zwane']);
        Matter::factory()->create(['title' => 'Something else entirely']);

        $found = $this->found($this->userIn(RoleEnum::Director), 'zwane');

        $this->assertSame(['Matters', 'Clients', 'Narrations', 'Bills'], array_keys($found));
        $this->assertSame([$matter->reference], $found['Matters']);
        $this->assertSame(['Nomvula Zwane'], $found['Clients']);
        $this->assertSame(['Consulted with Mrs Zwane about the arrest.'], $found['Narrations']);
        $this->assertSame(['INV/2026/0042 · Statement of account'], $found['Bills']);

        $response = $this->getJson(route('search', ['q' => 'zwane']));
        $response->assertJsonPath('groups.0.items.0.url', route('matter.show', $matter));
        $response->assertJsonPath('groups.1.items.0.url', route('client.show', $client));
    }

    public function test_it_gives_at_most_five_of_each_kind_and_nothing_for_a_single_letter(): void
    {
        $director = $this->userIn(RoleEnum::Director);
        $matter = Matter::factory()->create();

        foreach (range(1, 7) as $n) {
            Client::factory()->create(['first_name' => 'Thabo', 'last_name' => "Mokoena{$n}"]);
            Narration::factory()->for($matter)->create(['body' => "Thabo called, note {$n}"]);
        }

        $found = $this->found($director, 'Thabo');

        $this->assertCount(5, $found['Clients']);
        $this->assertCount(5, $found['Narrations']);
        $this->assertSame([], $this->found($director, 'T'));
        $this->assertSame([], $this->found($director, '   '));
    }

    public function test_it_finds_only_what_the_person_may_open(): void
    {
        $client = Client::factory()->create(['first_name' => 'Nomvula', 'last_name' => 'Zwane']);
        $mine = Matter::factory()->for($client)->create(['title' => 'Zwane eviction']);
        $other = Matter::factory()->for($client)->create(['title' => 'Zwane estate']);
        Narration::factory()->for($mine)->create(['body' => 'Zwane signed the mandate']);
        Narration::factory()->for($other)->create(['body' => 'Zwane brought the will']);
        Bill::factory()->for($mine)->issued('INV/2026/0042')->create(['addressee' => 'Mrs N Zwane']);

        // A messenger on one matter: that matter and its narrations, no clients, no bills.
        $messenger = $this->userIn(RoleEnum::Messenger);
        MatterAssignment::factory()->for($mine)->for($messenger)->create();

        $this->assertSame(
            ['Matters' => [$mine->reference], 'Narrations' => ['Zwane signed the mandate']],
            $this->found($messenger, 'zwane'),
        );

        // Finance: bills only, never the file.
        $this->assertSame(['Bills' => ['INV/2026/0042 · Statement of account']], $this->found($this->userIn(RoleEnum::Finance), 'zwane'));

        // An attorney on the matter sees its file and clients, but no bills.
        $attorney = $this->userIn(RoleEnum::Attorney);
        MatterAssignment::factory()->for($mine)->for($attorney)->create();
        $this->assertSame(['Matters', 'Clients', 'Narrations'], array_keys($this->found($attorney, 'zwane')));
    }

    public function test_the_search_bar_shows_for_those_with_something_to_search(): void
    {
        $this->actingAs($this->userIn(RoleEnum::Director))->get(route('dashboard'))->assertSee('siteSearch', false);
        $this->actingAs($this->userIn(RoleEnum::Finance))->get(route('dashboard'))->assertSee('siteSearch', false);
        $this->actingAs($this->userIn(RoleEnum::Messenger))->get(route('dashboard'))->assertDontSee('siteSearch', false);
    }
}
