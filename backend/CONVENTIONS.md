# Legal Case Management Platform — Coding Conventions

PHP/Laravel style, naming, and testing conventions for this repository.

Adapted from [Spatie's Laravel guidelines](https://spatie.be/guidelines/laravel) and
[version control guidelines](https://spatie.be/guidelines/version-control).

---

## 1. Stack

- API-first. Single Laravel app serving a versioned JSON API and the web app.
- Web: Laravel Blade / Livewire.
- Database: MySQL.
- Testing: PHPUnit.

## 2. Naming Conventions

`case` is a reserved word in PHP — a class cannot be named `Case`. The model for the `cases`
table is `LegalCase`.

| Type | Convention | Example |
|---|---|---|
| Model | Singular, PascalCase | `LegalCase`, `Narration`, `DiaryEntry`, `CaseDocument`, `User` |
| Table | Plural, snake_case | `cases`, `narrations`, `diary_entries`, `case_documents`, `users` |
| Controller | Plural resource + `Controller` | `LegalCasesController`, `NarrationsController` |
| Invokable/one-off controller | Verb phrase + `Controller` | `CompleteDiaryEntryController` |
| Action class | Verb phrase | `GenerateCaseReference`, `SendOverdueDiaryAlert` |
| Event | Past/present participle | `NarrationLogged`, `DiaryEntryOverdue` |
| Listener | Action + `Listener` | `NotifyAssigneeListener` |
| Artisan command | Verb phrase + `Command` | `SendOverdueAlertsCommand` |
| Mailable | Descriptive + `Mail` | `DiaryEntryOverdueMail` |
| Form Request | Action + `Request` | `StoreNarrationRequest` |
| Policy | Model + `Policy` | `LegalCasePolicy`, `DiaryEntryPolicy` |
| Enum | No prefix | `MatterType`, `UserRole` |
| Config file | kebab-case | `config/case-references.php` |
| Blade view | camelCase | `resources/views/legalCases/show.blade.php` |
| Web route URL | kebab-case | `/cases/{legalCase}/narrations` |
| Route name | camelCase | `cases.show` |
| API resource URL | Plural, kebab-case | `/api/v1/case-documents` |

## 3. General PHP Style

PSR-1, PSR-2, PSR-12. camelCase by default; kebab-case only for URLs and config file names.

Classes are not marked `final`.

```php
public ?string $referenceNote;
```

```php
public function scopeOverdue(Builder $query): void
{
    $query->where('due_date', '<', now())->where('status', 'pending');
}
```

```php
class DiaryEntry extends Model
{
    protected string $actionRequired;
}
```

Docblocks only where the signature doesn't already say everything.

```php
/** Generate the next sequential file reference for a matter type and year. */
public function generate(string $matterType, int $year): string
{
    // ...
}
```

Constructor property promotion, one property per line, trailing comma.

```php
class GenerateCaseReference
{
    public function __construct(
        protected string $matterType,
        protected int $year,
    ) {}
}
```

One `use` statement per trait.

```php
class LegalCase extends Model
{
    use HasFactory;
    use SoftDeletes;
}
```

String interpolation, not concatenation.

```php
$reference = "{$initials}/{$matterType}/{$sequence}/{$year}";
```

## 4. Control Flow

```php
$label = $isOverdue ? 'Overdue' : 'On track';
```

Guard clauses first, happy path last. Always use braces. No `else` — return early instead.

```php
public function markComplete(DiaryEntry $entry, User $user): void
{
    if ($entry->status !== 'pending') {
        throw new InvalidStateException('Only pending entries can be completed.');
    }

    $entry->update(['status' => 'completed', 'completed_by' => $user->id, 'completed_at' => now()]);
}
```

Separate `if` statements over compound conditions.

```php
if (! $legalCase->isOpen()) {
    return;
}

if (! $user->canNarrate($legalCase)) {
    return;
}
```

## 5. Comments

```php
// Space after // for a single-line comment.

/*
 * Block comment, single asterisk on the opening line.
 */
```

Extract a well-named method instead of commenting a block of code.

## 6. Enums

```php
enum MatterType: string
{
    case Civil = 'CIV';
    case Criminal = 'CRM';
    case MedicalNegligence = 'NEDMAG';
    case RoadAccidentFund = 'RAF';
}

enum UserRole: string
{
    case Director = 'director';
    case CandidateAttorney = 'ca';
    case LegalSecretary = 'legal_secretary';
    case Messenger = 'messenger';
}
```

## 7. Routing

Web routes: kebab-case URLs, camelCase parameters, HTTP verb first.

```php
Route::get('cases/{legalCase}', [LegalCasesController::class, 'show'])->name('cases.show');
Route::post('cases/{legalCase}/narrations', [NarrationsController::class, 'store']);
```

API routes: versioned, plural kebab-case.

```php
Route::prefix('v1')->group(function () {
    Route::apiResource('cases', LegalCasesController::class);
    Route::apiResource('cases.narrations', NarrationsController::class);
    Route::apiResource('case-documents', CaseDocumentsController::class);
});
```

## 8. Controllers

Name after the plural resource. Default CRUD verbs only (`index`, `store`, `show`, `update`,
`destroy`); anything else gets its own invokable controller.

```php
class NarrationsController
{
    public function store(StoreNarrationRequest $request, LegalCase $legalCase) { /* ... */ }
}

class CompleteDiaryEntryController
{
    public function __invoke(DiaryEntry $entry) { /* ... */ }
}
```

## 9. Views

camelCase filenames, four-space indent, no space after `@if`/`@foreach`.

```blade
@if($diaryEntry->isOverdue())
    <span class="badge-overdue">Overdue</span>
@endif
```

## 10. Validation

Array notation for form request rules. Custom rule names in snake_case.

```php
public function rules(): array
{
    return [
        'action_required' => ['required', 'string', 'max:500'],
        'due_date' => ['required', 'date', 'after_or_equal:today'],
    ];
}
```

## 11. Authorization

One policy per model. camelCase abilities. `view`, not `show`.

```php
class LegalCasePolicy
{
    public function view(User $user, LegalCase $legalCase): bool
    {
        return $user->role === UserRole::Director || $legalCase->assignedUsers->contains($user);
    }
}
```

## 12. Configuration

kebab-case filenames, snake_case keys. No `env()` outside config files.

```php
// config/case-references.php
return [
    'starting_sequence' => env('CASE_REFERENCE_START', 1),
];
```

## 13. Artisan Commands

kebab-case names. Always output something on completion.

```php
// php artisan diary:send-overdue-alerts
public function handle(): void
{
    $this->comment('Checking for overdue diary entries...');

    DiaryEntry::overdue()->each(function (DiaryEntry $entry) {
        $this->info("Alerting {$entry->assignedTo->name} — {$entry->legalCase->reference}");
    });

    $this->comment('Done.');
}
```

## 14. Testing

PHPUnit. Laravel's standard `TestCase` classes, run via `php artisan test`.

Every feature PR includes:
- Unit tests for new/changed Action classes — pure logic, dependencies mocked.
- One feature test per feature — route → controller → database (`RefreshDatabase`) → response.

`tests/Unit/...` mirrors `app/...`. `tests/Feature/...` is grouped by resource. Test methods are
`test_` prefixed, snake_case, describing behavior.

```php
// tests/Feature/Diary/CompleteDiaryEntryTest.php
class CompleteDiaryEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_marks_a_diary_entry_complete_and_records_who_completed_it(): void
    {
        $entry = DiaryEntry::factory()->pending()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patchJson("/api/v1/diary-entries/{$entry->id}", ['status' => 'completed'])
            ->assertOk();

        $entry->refresh();

        $this->assertSame('completed', $entry->status);
        $this->assertSame($user->id, $entry->completed_by);
    }
}
```

## 15. Code Organization

Standard Laravel structure.

```
app/
  Http/
    Controllers/     (LegalCasesController, NarrationsController, DiaryEntriesController, ...)
    Requests/         (StoreNarrationRequest, ...)
  Models/             (LegalCase, Narration, DiaryEntry, CaseDocument, User)
  Services/
    Cases/            (GenerateCaseReference, ...)
    Diary/            (SendOverdueDiaryAlert, CompleteDiaryEntry, ...)
  Policies/
routes/
  web.php
  api.php
```

## 16. Git & Commits

Present-tense, descriptive commit messages. Granular commits over one large one. Branching,
PR, and release process: `CONTRIBUTING.md`.

```
feat: add narration endpoint
fix: add narration date validation
```
