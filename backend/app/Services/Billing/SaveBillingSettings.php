<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingTiming;
use App\Enums\Billing\DrawingFeeBase;
use App\Settings\BillingSettings;
use Illuminate\Support\Facades\Auth;

class SaveBillingSettings
{
    public function __construct(
        protected BillingSettings $settings,
    ) {}

    /**
     * Change how the firm bills. Agreements already signed keep their own
     * percentage, so a new default applies to agreements recorded after it.
     * What changed is written to the audit log, since it changes what clients pay.
     *
     * @param  array{contingencyPercent: float, estatePercent: float, drawingFeePercent: float, drawingFeeBase: DrawingFeeBase, billingTiming: BillingTiming}  $values
     */
    public function save(array $values): BillingSettings
    {
        $before = $this->snapshot();

        $this->settings->fill($values)->save();

        $after = $this->snapshot();
        $changed = array_keys(array_diff_assoc($after, $before));

        if ($changed !== []) {
            activity('billing')
                ->event('updated')
                ->withProperties([
                    'old' => array_intersect_key($before, array_flip($changed)),
                    'attributes' => array_intersect_key($after, array_flip($changed)),
                ])
                ->log(sprintf('%s changed the billing settings', Auth::user()?->name ?? 'System'));
        }

        return $this->settings;
    }

    /** @return array<string, string> */
    protected function snapshot(): array
    {
        return collect($this->settings->toArray())
            ->map(fn (mixed $value) => $value instanceof \BackedEnum ? $value->value : (string) $value)
            ->all();
    }
}
