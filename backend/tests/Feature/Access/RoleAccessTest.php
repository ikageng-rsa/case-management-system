<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Enums\Auth\Role as RoleEnum;
use App\Models\Billing\Bill;
use App\Models\Billing\FeeAgreement;
use App\Models\Matter\Matter;
use App\Models\Matter\MatterAssignment;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/* What each default role can open, and that amounts show only to roles that see bills. */
class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    private const OK = 200;

    private const NO = 403;

    private const MOVED = 302;

    /** Pages in the order of the columns below. The clients page and a matter's billing lead on to another page for those let in. */
    private const PAGES = ['dashboard', 'matters', 'matter', 'documents', 'clients', 'bills', 'matter billing', 'bill', 'payments', 'reports', 'users', 'reference data', 'settings', 'audit log'];

    /** @var array<string, list<int>> */
    private const EXPECTED = [
        'director' => [self::OK, self::OK, self::OK, self::OK, self::MOVED, self::OK, self::MOVED, self::OK, self::OK, self::OK, self::OK, self::OK, self::OK, self::OK],
        'attorney' => [self::OK, self::OK, self::OK, self::OK, self::MOVED, self::NO, self::MOVED, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::OK],
        'candidate_attorney' => [self::OK, self::OK, self::OK, self::OK, self::MOVED, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO],
        'legal_secretary' => [self::OK, self::OK, self::OK, self::OK, self::MOVED, self::NO, self::MOVED, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO],
        'manager' => [self::OK, self::OK, self::OK, self::OK, self::MOVED, self::NO, self::NO, self::NO, self::NO, self::NO, self::OK, self::OK, self::NO, self::OK],
        'finance' => [self::OK, self::NO, self::NO, self::NO, self::NO, self::OK, self::OK, self::OK, self::OK, self::OK, self::NO, self::OK, self::NO, self::NO],
        'messenger' => [self::OK, self::OK, self::OK, self::OK, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO, self::NO],
    ];

    /** Roles that work on files are put on the matter; finance never is. */
    private const ASSIGNED = ['attorney', 'candidate_attorney', 'messenger'];

    protected Matter $matter;

    protected Bill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RoleSeeder::class);

        $this->matter = Matter::factory()->create();
        FeeAgreement::factory()->for($this->matter)->contingency(25)->finalised(48000000)->create();
        $this->bill = Bill::factory()->for($this->matter)->issued('INV/2026/0042')->create(['total_cents' => 1234500]);
    }

    protected function userIn(string $role): User
    {
        $user = tap(User::factory()->create())->assignRole($role);

        if (in_array($role, self::ASSIGNED, true)) {
            MatterAssignment::factory()->for($this->matter)->for($user)->create();
        }

        return $user;
    }

    /** @return array<string, string> */
    protected function urls(): array
    {
        return array_combine(self::PAGES, [
            route('dashboard'),
            route('matters'),
            route('matter.show', $this->matter),
            route('documents'),
            route('clients'),
            route('bills'),
            route('billing.matter', $this->matter),
            route('bill.show', $this->bill),
            route('payments'),
            route('reports'),
            route('users'),
            route('reference-data'),
            route('settings'),
            route('audit-log'),
        ]);
    }

    public function test_every_default_role_is_covered(): void
    {
        $this->assertEqualsCanonicalizing(array_column(RoleEnum::cases(), 'value'), array_keys(self::EXPECTED));
    }

    public function test_each_role_opens_only_the_pages_meant_for_it(): void
    {
        $actual = [];

        foreach (array_keys(self::EXPECTED) as $role) {
            $user = $this->userIn($role);

            foreach ($this->urls() as $url) {
                $actual[$role][] = $this->actingAs($user)->get($url)->getStatusCode();
            }
        }

        $this->assertSame(self::EXPECTED, $actual, 'Columns: '.implode(', ', self::PAGES));
    }

    public function test_amounts_show_only_to_roles_that_see_bills(): void
    {
        $amounts = ['R 12 345.00', 'R 480 000.00', 'R 120 000.00', 'INV/2026/0042'];

        foreach (['attorney', 'candidate_attorney', 'legal_secretary', 'manager', 'messenger'] as $role) {
            $user = $this->userIn($role);

            foreach ([route('dashboard'), route('matters'), route('matter.show', $this->matter), route('matter.show', [$this->matter, 'tab' => 'billing'])] as $url) {
                $response = $this->actingAs($user)->get($url)->assertOk();

                foreach ($amounts as $amount) {
                    $response->assertDontSee($amount);
                }
            }
        }

        $director = $this->userIn('director');
        $this->actingAs($director)->get(route('matter.show', [$this->matter, 'tab' => 'billing']))->assertSee('R 12 345.00')->assertSee('R 480 000.00');
        $this->actingAs($this->userIn('finance'))->get(route('billing.matter', $this->matter))->assertSee('R 12 345.00')->assertSee('R 480 000.00');
    }
}
