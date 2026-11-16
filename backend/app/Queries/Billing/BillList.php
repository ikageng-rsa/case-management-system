<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\BillStatus;
use App\Models\Billing\Bill;
use App\Models\User\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/*
 * The bills on the matters whose billing a person works on, newest first: every
 * matter for someone who works on all billing, otherwise the matters they may
 * see. Narrowed by where a bill stands, and by its number, who it is addressed
 * to, or its matter.
 */
class BillList
{
    public const PER_PAGE = 25;

    /** The lists a bill can be found under, in the order they are offered. */
    public const SHOWING = ['all' => 'All', 'draft' => 'Drafts', 'owed' => 'Owed', 'settled' => 'Settled', 'void' => 'Void'];

    /** @return array{bills: LengthAwarePaginator, counts: array<string, int>} */
    public function for(User $user, string $show = 'all', ?string $search = null): array
    {
        $inScope = fn (): Builder => Bill::query()
            ->whereHas('matter', fn (Builder $matters) => $matters->withBillingReachedBy($user))
            ->when(filled($search), fn (Builder $query) => $this->search($query, (string) $search));

        return [
            'bills' => $this->showing($inScope(), $show)
                ->with('matter.client')
                ->latest()
                ->latest('id')
                ->paginate(self::PER_PAGE)
                ->withQueryString(),
            'counts' => collect(array_keys(self::SHOWING))
                ->mapWithKeys(fn (string $key) => [$key => $this->showing($inScope(), $key)->count()])
                ->all(),
        ];
    }

    protected function showing(Builder $query, string $show): Builder
    {
        return match ($show) {
            'draft' => $query->where('status', BillStatus::Draft),
            'owed' => $query->where('status', BillStatus::Issued)->whereColumn('paid_cents', '<', 'total_cents'),
            'settled' => $query->where('status', BillStatus::Issued)->whereColumn('paid_cents', '>=', 'total_cents'),
            'void' => $query->where('status', BillStatus::Void),
            default => $query,
        };
    }

    protected function search(Builder $query, string $term): void
    {
        $like = '%'.addcslashes(trim($term), '%_\\').'%';

        $query->where(fn (Builder $query) => $query
            ->where('number', 'like', $like)
            ->orWhere('addressee', 'like', $like)
            ->orWhereHas('matter', fn (Builder $matters) => $matters->search($term)));
    }
}
