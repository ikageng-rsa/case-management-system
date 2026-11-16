<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Settings\InvoiceSettings;
use BackedEnum;
use Illuminate\Support\Facades\Auth;

class SaveInvoiceSettings
{
    public function __construct(
        protected InvoiceSettings $settings,
    ) {}

    /**
     * Change how bills look and how statements ask to be paid. The account
     * clients pay into is money's destination, so every change is audited.
     *
     * @param  array<string, mixed>  $values
     */
    public function save(array $values): InvoiceSettings
    {
        $before = $this->snapshot();

        $this->settings->fill($values)->save();

        $after = $this->snapshot();
        $changed = array_keys(array_diff_assoc($after, $before));

        if ($changed !== []) {
            activity('settings')
                ->event('updated')
                ->withProperties([
                    'old' => array_intersect_key($before, array_flip($changed)),
                    'attributes' => array_intersect_key($after, array_flip($changed)),
                ])
                ->log(sprintf('%s changed the invoice settings', Auth::user()?->name ?? 'System'));
        }

        return $this->settings;
    }

    /** @return array<string, string> */
    protected function snapshot(): array
    {
        return collect($this->settings->toArray())
            ->map(fn (mixed $value) => $value instanceof BackedEnum ? $value->value : (string) $value)
            ->all();
    }
}
