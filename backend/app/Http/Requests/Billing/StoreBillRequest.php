<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillKind;
use App\Enums\Billing\TariffScale;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreBillRequest extends FormRequest
{
    protected $errorBag = 'bill';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in([BillKind::Statement->value, BillKind::BillOfCost->value])],
            'addressee' => ['required', 'string', 'max:255'],
            // Left empty, a statement goes to the client's address on file.
            'address' => ['nullable', 'string', 'max:1000'],
            'scale' => ['nullable', Rule::enum(TariffScale::class)],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            // An hourly rate in rand for each person whose time on top is billed.
            'time_rates' => ['nullable', 'array'],
            'time_rates.*' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:1000000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['time_rates.*.numeric' => 'An hourly rate is an amount in rand.'];
    }

    public function kind(): BillKind
    {
        return BillKind::from($this->validated('kind'));
    }

    public function scale(): ?TariffScale
    {
        return $this->filled('scale') ? TariffScale::from($this->validated('scale')) : null;
    }

    public function from(): ?Carbon
    {
        return $this->filled('from') ? Carbon::parse($this->validated('from')) : null;
    }

    public function to(): ?Carbon
    {
        return $this->filled('to') ? Carbon::parse($this->validated('to')) : null;
    }

    /** @return array<string, int> cents an hour, by person, for the rates that were given */
    public function timeRates(): array
    {
        return collect($this->validated('time_rates') ?? [])
            ->filter(fn ($rate) => $rate !== null && $rate !== '')
            ->map(fn ($rate) => (int) round((float) $rate * 100))
            ->all();
    }
}
