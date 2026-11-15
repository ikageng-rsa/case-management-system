<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Settings\FirmSettings;
use Illuminate\Support\Facades\Auth;

class SaveFirmSettings
{
    public function __construct(
        protected FirmSettings $settings,
    ) {}

    /**
     * Change the firm's details. Every bill printed afterwards carries the new
     * ones, including bills issued before, so the change is written to the
     * audit log.
     *
     * @param  array{name: string, tagline: ?string, addressLines: list<string>, phone: ?string, email: ?string}  $values
     */
    public function save(array $values): FirmSettings
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
                ->log(sprintf('%s changed the firm details', Auth::user()?->name ?? 'System'));
        }

        return $this->settings;
    }

    /** @return array<string, string> */
    protected function snapshot(): array
    {
        return collect($this->settings->toArray())
            ->map(fn (mixed $value) => is_array($value) ? implode("\n", $value) : (string) $value)
            ->all();
    }
}
