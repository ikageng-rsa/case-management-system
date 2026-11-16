<?php

declare(strict_types=1);

namespace App\Queries\Billing;

use App\Enums\Billing\FeeAgreementKind;
use App\Models\Billing\Disbursement;
use App\Models\Matter\Matter;
use App\Models\Narration\Narration;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/* Matters with work or disbursements not yet on a bill, oldest first, valued on the tariff. */
class WorkToBill
{
    public const PER_PAGE = 8;

    public function __construct(
        protected NarrationPrice $price,
    ) {}

    /** @return array{rows: LengthAwarePaginator, valueCents: int, unpricedCount: int} */
    public function for(User $user, int $page = 1): array
    {
        $unbilled = fn (Builder $query) => $query->whereDoesntHave('billLines', fn (Builder $lines) => $lines->whereNotNull('claim'));
        $work = fn (Builder $query) => $unbilled($query->whereHas('activityType', fn (Builder $types) => $types->billable()));

        $rows = Matter::query()
            ->withBillingReachedBy($user)
            ->where(fn (Builder $query) => $query->whereHas('narrations', $work)->orWhereHas('disbursements', $unbilled))
            ->with([
                'client',
                'feeAgreements' => fn ($query) => $query->active(),
                'narrations' => fn ($query) => $work($query->getQuery())->with(['activityType', 'author.roles']),
                'disbursements' => fn ($query) => $unbilled($query->getQuery()),
            ])
            ->get()
            ->map(fn (Matter $matter) => $this->row($matter))
            ->filter(fn (array $row) => $row['entries'] > 0)
            ->sortBy('since')
            ->values();

        return [
            'rows' => new LengthAwarePaginator($rows->forPage($page, self::PER_PAGE)->values(), $rows->count(), self::PER_PAGE, $page),
            'valueCents' => (int) $rows->sum(fn (array $row) => $row['workCents'] + $row['disbursementCents']),
            'unpricedCount' => (int) $rows->sum('unpricedCount'),
        ];
    }

    /** @return array<string, mixed> */
    protected function row(Matter $matter): array
    {
        $agreement = $matter->feeAgreements->first();

        // Under an estate agreement the work is covered by the share, so only the costs paid out are left to bill.
        $narrations = $agreement?->kind === FeeAgreementKind::Estate ? collect() : $matter->narrations;

        $prices = $narrations->map(fn (Narration $narration) => $this->price->for($narration->setRelation('matter', $matter)));
        $disbursements = $matter->disbursements;

        return [
            'matter' => $matter,
            'agreement' => $agreement,
            'entries' => $narrations->count() + $disbursements->count(),
            'since' => collect([
                $narrations->min('occurred_at'),
                $disbursements->min(fn (Disbursement $disbursement) => $disbursement->incurred_on),
            ])->filter()->min(),
            'workCents' => (int) $prices->where('priced', true)->sum('amountCents'),
            'unpricedCount' => $prices->where('priced', false)->count(),
            'disbursementCents' => (int) $disbursements->sum('amount_cents'),
        ];
    }
}
