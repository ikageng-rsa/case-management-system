<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\TariffAdjustmentKind;
use App\Models\User\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTariffAdjustmentsRequest extends FormRequest
{
    protected $errorBag = 'tariff_adjustments';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * One markup per role: a percentage of the rate or an amount in rand on
     * each unit, either of which may be negative. No kind means none.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'adjustments' => ['required', 'array'],
            'adjustments.*.kind' => ['nullable', Rule::enum(TariffAdjustmentKind::class)],
            'adjustments.*.value' => ['nullable', 'required_with:adjustments.*.kind', 'numeric', 'decimal:0,2', 'min:-100000', 'max:100000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'adjustments.*.value.required_with' => 'Give the markup an amount, or set it to none.',
            'adjustments.*.value.decimal' => 'A markup has at most two decimals.',
        ];
    }

    /**
     * The markups by role id, in hundredths of a percent or in cents, with null
     * for a role whose markup is cleared. Unknown roles are ignored.
     *
     * @return array<int, array{kind: TariffAdjustmentKind, value: int}|null>
     */
    public function adjustments(): array
    {
        $roleIds = Role::query()->pluck('id')->all();

        return collect($this->validated('adjustments'))
            ->filter(fn ($adjustment, $roleId) => in_array((int) $roleId, $roleIds, true))
            ->mapWithKeys(fn (array $adjustment, $roleId) => [(int) $roleId => filled($adjustment['kind'] ?? null)
                ? ['kind' => TariffAdjustmentKind::from($adjustment['kind']), 'value' => (int) round((float) $adjustment['value'] * 100)]
                : null])
            ->all();
    }
}
