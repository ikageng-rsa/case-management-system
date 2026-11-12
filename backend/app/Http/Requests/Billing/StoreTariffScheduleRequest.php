<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Models\Billing\TariffSchedule;
use App\Rules\Document\AllowedDocument;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreTariffScheduleRequest extends FormRequest
{
    protected $errorBag = 'tariff_schedule_new';

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
            'name' => ['required', 'string', 'max:255'],
            'effective_from' => ['required', 'date', function (string $attribute, mixed $value, Closure $fail): void {
                // Compared by day, since a date column is stored with a time on some databases.
                if (TariffSchedule::query()->whereDate('effective_from', Carbon::parse($value))->exists()) {
                    $fail('Another tariff already starts on this date.');
                }
            }],
            'file' => ['required', 'file', 'extensions:csv', 'max:2048', new AllowedDocument],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.extensions' => 'Upload the tariff as a .csv file in the template\'s layout.',
        ];
    }

    public function effectiveFrom(): Carbon
    {
        return Carbon::parse($this->validated('effective_from'))->startOfDay();
    }
}
