<?php

declare(strict_types=1);

namespace App\Queries\Search;

use App\Enums\Auth\Permission;
use App\Models\Billing\Bill;
use App\Models\Client\Client;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use App\Queries\Clients\MatchingClients;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/* What the search bar finds for a person: a few of each kind of record they may open. */
class SiteSearch
{
    public const PER_KIND = 5;

    public const MIN_LENGTH = 2;

    public function __construct(
        protected MatchingClients $matchingClients,
    ) {}

    /** @return list<array{label: string, items: list<array{title: string, meta: string, url: string}>}> */
    public function for(User $user, string $term): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return [];
        }

        $groups = [
            'Matters' => $user->worksOnMatters() ? $this->matters($user, $term) : [],
            'Clients' => $user->can(Permission::ViewClients->value) ? $this->clients($term) : [],
            'Narrations' => $user->worksOnMatters() ? $this->narrations($user, $term) : [],
            'Bills' => $user->can(Permission::ViewBills->value) ? $this->bills($user, $term) : [],
        ];

        return collect($groups)
            ->filter()
            ->map(fn (array $items, string $label) => ['label' => $label, 'items' => $items])
            ->values()
            ->all();
    }

    protected function visibleMatters(User $user): \Closure
    {
        return fn (Builder $query) => $query->when(! $user->can('viewAny', Matter::class), fn (Builder $query) => $query->assignedTo($user));
    }

    /** @return list<array<string, string>> */
    protected function matters(User $user, string $term): array
    {
        return Matter::query()
            ->tap($this->visibleMatters($user))
            ->search($term)
            ->with('client')
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Matter $matter) => [
                'title' => $matter->reference,
                'meta' => collect([$matter->client?->full_name, $matter->title])->filter()->implode(' · '),
                'url' => route('matter.show', $matter),
            ])
            ->all();
    }

    /** @return list<array<string, string>> */
    protected function clients(string $term): array
    {
        return $this->matchingClients->for($term)
            ->withCount('matters')
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Client $client) => [
                'title' => $client->full_name,
                'meta' => $client->matters_count.' '.Str::plural('matter', $client->matters_count),
                'url' => route('client.show', $client),
            ])
            ->all();
    }

    /** @return list<array<string, string>> */
    protected function narrations(User $user, string $term): array
    {
        return Narration::query()
            ->where('body', 'like', $this->like($term))
            ->whereHas('matter', $this->visibleMatters($user))
            ->with(['matter', 'author'])
            ->latest('occurred_at')
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Narration $narration) => [
                'title' => Str::limit($narration->body, 90),
                'meta' => collect([$narration->matter->reference, $narration->author?->name, $narration->occurred_at->format('j M Y')])->filter()->implode(' · '),
                'url' => route('matter.show', $narration->matter),
            ])
            ->all();
    }

    /** @return list<array<string, string>> */
    protected function bills(User $user, string $term): array
    {
        $like = $this->like($term);

        return Bill::query()
            ->whereHas('matter', fn (Builder $matters) => $matters->withBillingReachedBy($user))
            ->where(fn (Builder $query) => $query->where('number', 'like', $like)->orWhere('addressee', 'like', $like))
            ->with('matter')
            ->latest()
            ->limit(self::PER_KIND)
            ->get()
            ->map(fn (Bill $bill) => [
                'title' => ($bill->number ?? 'Draft').' · '.$bill->kind->label(),
                'meta' => $bill->addressee.' · '.$bill->matter->reference,
                'url' => route('bill.show', $bill),
            ])
            ->all();
    }

    protected function like(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
