<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Auth\Role as RoleEnum;
use App\Enums\Client\ContactKind;
use App\Enums\Client\PopiaConsentMethod;
use App\Enums\Document\DocumentKind;
use App\Enums\Matter\Assignment;
use App\Enums\Narration\ActivityMeasure;
use App\Models\ActivityType;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Court;
use App\Models\DiaryEntry;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\MatterType;
use App\Models\Narration;
use App\Models\PopiaConsent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

/*
 * Demo data for local and staging use. Deliberately not called from
 * DatabaseSeeder — run it on its own with `php artisan db:seed --class=DemoDataSeeder`,
 * after the usual db:seed has put the reference data (roles, matter types,
 * activity types, courts) in place.
 */
class DemoDataSeeder extends Seeder
{
    /** Next file number per matter type, so references stay sequential. */
    protected array $sequences = [];

    /*
     * Of the plan's open matters (indices 0, 1, 2, 3, 5, 6 — see seedMatters),
     * only these get an overdue diary entry. The rest stay on track, and index
     * 0 is left alone deliberately so its "prescribing soon" state isn't
     * masked by an overdue one — the dashboard should show a mix of statuses,
     * not every open matter in the same state.
     */
    protected const OVERDUE_MATTER_INDEXES = [1, 5];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DemoDataSeeder must not be run in production.');
        }

        $matterTypes = MatterType::query()->get()->keyBy('code');
        $courts = Court::query()->get();
        $activityTypes = ActivityType::query()->get()->keyBy('code');

        if ($matterTypes->isEmpty() || $courts->isEmpty() || $activityTypes->isEmpty()) {
            throw new RuntimeException('Run the main db:seed first — DemoDataSeeder needs matter types, courts and activity types to already exist.');
        }

        $staff = $this->seedStaff();

        $this->command?->info('Seeded the demo staff.');

        $clients = $this->seedClients();

        $this->command?->info("Seeded {$clients->count()} clients.");

        $matters = $this->seedMatters($clients, $matterTypes, $courts, $staff);

        foreach ($matters as $index => $matter) {
            $this->seedWork($matter, $activityTypes, $staff, $index);
        }

        $this->command?->info("Seeded {$matters->count()} matters with narrations, diary entries and documents.");
        $this->command?->comment('Sign in as director@demo.test with the password "password".');
    }

    /** @return Collection<int, User> */
    protected function seedStaff(): Collection
    {
        return collect([
            ['name' => 'Thandiwe Mokoena', 'email' => 'director@demo.test', 'role' => RoleEnum::Director],
            ['name' => 'Sipho Ndlovu', 'email' => 'attorney@demo.test', 'role' => RoleEnum::Attorney],
            ['name' => 'Lerato Khumalo', 'email' => 'candidate@demo.test', 'role' => RoleEnum::CandidateAttorney],
            ['name' => 'Johan van Wyk', 'email' => 'secretary@demo.test', 'role' => RoleEnum::Secretary],
        ])->map(function (array $person) {
            $user = User::firstOrCreate(
                ['email' => $person['email']],
                User::factory()->raw(['name' => $person['name'], 'email' => $person['email']]),
            );

            $user->syncRoles([$person['role']->value]);

            return $user;
        });
    }

    /** @return Collection<int, Client> */
    protected function seedClients(): Collection
    {
        $individuals = Client::factory()->count(6)->create();
        $entities = Client::factory()->entity()->count(2)->create();

        return $individuals->concat($entities)->values()->each(function (Client $client, int $index) {
            ClientContact::factory()->for($client)->primary()->create([
                'kind' => ContactKind::Email,
                'value' => Str::slug($client->full_name, '.').'@example.test',
            ]);

            ClientContact::factory()->for($client)->mobile()->primary()->create();

            ClientContact::factory()->for($client)->create([
                'kind' => ContactKind::Physical,
                'value' => fake()->streetAddress().', '.fake()->city(),
            ]);

            /*
             * The last client has refused consent and another has withdrawn
             * it, so the demo exercises the paths where processing has to be
             * blocked rather than only the happy one.
             */
            $consent = PopiaConsent::factory()->for($client);

            $consent = match ($index) {
                6 => $consent->withdrawn(),
                7 => $consent->refused(),
                default => $consent,
            };

            $consent->create(['method' => fake()->randomElement(PopiaConsentMethod::cases())]);
        });
    }

    /** @return Collection<int, Matter> */
    protected function seedMatters(
        Collection $clients,
        Collection $matterTypes,
        Collection $courts,
        Collection $staff,
    ): Collection {
        $plan = [
            ['type' => 'RAF', 'title' => 'Claim against the Road Accident Fund', 'state' => 'prescribing'],
            ['type' => 'RAF', 'title' => 'Loss of support claim', 'state' => 'open'],
            ['type' => 'NEDMAG', 'title' => 'Delayed diagnosis claim', 'state' => 'open'],
            ['type' => 'CIV', 'title' => 'Breach of lease agreement', 'state' => 'open'],
            ['type' => 'CIV', 'title' => 'Recovery of outstanding fees', 'state' => 'closed'],
            ['type' => 'CRM', 'title' => 'Defence on a charge of fraud', 'state' => 'open'],
            ['type' => 'CONV', 'title' => 'Transfer of residential property', 'state' => 'open'],
            ['type' => 'OPIN', 'title' => 'Opinion on restraint of trade', 'state' => 'closed'],
        ];

        return collect($plan)->map(function (array $entry, int $index) use ($clients, $matterTypes, $courts, $staff) {
            $type = $matterTypes[$entry['type']];
            $client = $clients[$index % $clients->count()];
            $responsible = $staff[$index % 3];

            $matter = $this->createMatter($entry, $type, $client, $courts, $responsible);

            $this->assignStaff($matter, $responsible, $staff);

            return $matter;
        });
    }

    protected function createMatter(
        array $entry,
        MatterType $type,
        Client $client,
        Collection $courts,
        User $responsible,
    ): Matter {
        $sequence = $this->nextSequence($type);
        $initials = $this->initials($responsible->name);
        $year = now()->year;

        $factory = Matter::factory()
            ->for($client)
            ->for($type, 'matterType');

        if ($entry['state'] === 'closed') {
            $factory = $factory->closed();
        }

        /*
         * One matter is deliberately days from prescribing, so the diary and
         * prescription warnings have something to show.
         */
        $prescribesAt = match (true) {
            $entry['state'] === 'prescribing' => now()->addDays(21),
            $type->prescribes() => now()->addMonths($type->default_prescription_months),
            default => null,
        };

        return $factory->create([
            'reference' => "{$initials}/{$type->code}/".str_pad((string) $sequence, 4, '0', STR_PAD_LEFT)."/{$year}",
            'sequence_number' => $sequence,
            'opened_year' => $year,
            'title' => $entry['title'],
            'court_id' => $type->is_litigation ? $courts->random()->id : null,
            'prescribes_at' => $prescribesAt,
            'instructed_at' => now()->subMonths(fake()->numberBetween(1, 18)),
        ]);
    }

    protected function assignStaff(Matter $matter, User $responsible, Collection $staff): void
    {
        MatterAssignment::factory()->for($matter)->for($responsible)
            ->capacity(Assignment::Responsible)->create();

        MatterAssignment::factory()->for($matter)->for($staff->first())
            ->capacity(Assignment::Supervising)->create();

        if (fake()->boolean(40)) {
            MatterAssignment::factory()->for($matter)->for($staff->last())
                ->capacity(Assignment::Assisting)->create();
        }
    }

    protected function seedWork(Matter $matter, Collection $activityTypes, Collection $staff, int $matterIndex): void
    {
        $author = $matter->load('assignments')->responsibleAttorney() ?? $staff->first();

        foreach (range(1, fake()->numberBetween(3, 6)) as $index) {
            $this->seedNarration($matter, $activityTypes, $author, $index);
        }

        $this->seedDiaryEntries($matter, $author, $matterIndex);
        $this->seedDocuments($matter, $author);
    }

    protected function seedNarration(Matter $matter, Collection $activityTypes, User $author, int $index): void
    {
        $type = $activityTypes->random();

        Narration::factory()
            ->for($matter)
            ->for($type, 'activityType')
            ->create([
                'author_id' => $author->id,
                'body' => $this->narrationBody($type->code),
                'quantity' => $this->quantityFor($type->measure),
                'occurred_at' => now()->subDays($index * fake()->numberBetween(3, 14)),
                'court_id' => $type->requires_court ? $matter->court_id : null,
            ]);

        /*
         * An appearance produces the order handed down. Media hangs off the
         * matter, so it is filed there rather than against the narration.
         */
        if ($type->requires_court && $matter->court_id !== null) {
            $this->storeDocument($matter, $author, DocumentKind::CourtOrder, 'court-order.pdf');
        }
    }

    protected function seedDiaryEntries(Matter $matter, User $author, int $matterIndex): void
    {
        if (! $matter->isOpen()) {
            DiaryEntry::factory()->for($matter)->create([
                'assigned_to' => $author->id,
                'body' => 'Close the file and archive the record.',
                'completed_at' => now()->subDays(fake()->numberBetween(1, 30)),
                'completed_by' => $author->id,
            ]);

            return;
        }

        DiaryEntry::factory()->for($matter)->create([
            'assigned_to' => $author->id,
            'body' => 'Follow up on outstanding documents from the client.',
            'due_at' => now()->addDays(fake()->numberBetween(2, 21)),
        ]);

        if (! in_array($matterIndex, self::OVERDUE_MATTER_INDEXES, true)) {
            return;
        }

        DiaryEntry::factory()->for($matter)->create([
            'assigned_to' => $author->id,
            'body' => 'Serve the notice of intention to defend.',
            'due_at' => now()->subDays(fake()->numberBetween(1, 14)),
        ]);
    }

    protected function seedDocuments(Matter $matter, User $author): void
    {
        $this->storeDocument($matter, $author, DocumentKind::Mandate, 'signed-mandate.pdf');
        $this->storeDocument($matter, $author, DocumentKind::Correspondence, 'letter-of-demand.pdf');

        if (fake()->boolean(50)) {
            $this->storeDocument($matter, $author, DocumentKind::Evidence, 'medical-report.pdf');
        }
    }

    /*
     * Goes through the real media library path so the demo carries files that
     * actually exist on the documents disk rather than rows pointing at
     * nothing.
     */
    protected function storeDocument(
        Matter $matter,
        User $author,
        DocumentKind $kind,
        string $filename,
    ): void {
        $path = tempnam(sys_get_temp_dir(), 'demo-document');
        file_put_contents($path, "Demo {$kind->value} — {$filename}\n".fake()->paragraph());

        try {
            $matter->addMedia($path)
                ->usingFileName($filename)
                ->usingName(pathinfo($filename, PATHINFO_FILENAME))
                ->withCustomProperties([
                    'kind' => $kind->value,
                    'uploaded_by' => $author->getKey(),
                ])
                ->toMediaCollection(Matter::DOCUMENTS);
        } finally {
            @unlink($path);
        }
    }

    /*
     * Carries on from whatever is already in the table, so running the seeder
     * a second time adds more demo matters instead of colliding with the
     * unique file number per type and year.
     */
    protected function nextSequence(MatterType $type): int
    {
        $this->sequences[$type->code] ??= Matter::withTrashed()
            ->where('matter_type_id', $type->id)
            ->where('opened_year', now()->year)
            ->max('sequence_number') ?? 0;

        return ++$this->sequences[$type->code];
    }

    protected function initials(string $name): string
    {
        return Str::of($name)
            ->explode(' ')
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->join('');
    }

    protected function narrationBody(string $code): string
    {
        return match ($code) {
            'CONS' => 'Consultation with the client on the merits and the way forward.',
            'DRAFT' => 'Drafting the particulars of claim.',
            'CALL' => 'Telephone attendance on the correspondent attorney.',
            'APP' => 'Appearance on the opposed motion roll.',
            'PERUSE' => 'Perusal of the hospital records received under subpoena.',
            'CORR' => 'Correspondence with the opposing attorney.',
            'TRAVEL' => 'Travel to court and return.',
            'FILE' => 'Filing and service of the notice of bar.',
            default => 'Administrative attendance on the file.',
        };
    }

    protected function quantityFor(ActivityMeasure $measure): int
    {
        return match ($measure) {
            ActivityMeasure::Minutes => fake()->numberBetween(6, 180),
            ActivityMeasure::Page => fake()->numberBetween(1, 60),
            ActivityMeasure::Kilometre => fake()->numberBetween(5, 200),
            ActivityMeasure::Item => fake()->numberBetween(1, 10),
        };
    }
}
