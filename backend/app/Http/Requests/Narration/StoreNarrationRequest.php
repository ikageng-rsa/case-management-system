<?php

declare(strict_types=1);

namespace App\Http\Requests\Narration;

use App\Enums\Narration\TimeUnit;
use App\Models\Reference\ActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreNarrationRequest extends FormRequest
{
    protected $errorBag = 'narration';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'activity_type_id' => ['required', 'integer', Rule::exists('activity_types', 'id')->withoutTrashed()],
            'body' => ['required', 'string', 'max:5000'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            // The time taken, for work that charges time on top, in minutes or hours.
            'time_value' => ['nullable', 'numeric', 'gt:0', 'decimal:0,2', 'max:1440'],
            'time_unit' => ['nullable', 'required_with:time_value', Rule::enum(TimeUnit::class)],
        ];
    }

    /**
     * An appearance is before a court, and the court is the matter's own: it is set
     * once on the matter, not repeated on every narration.
     *
     * @return array<int, callable>
     */
    /** The time taken in minutes, for work whose kind charges time on top; otherwise none. */
    public function minutes(): ?int
    {
        $unit = TimeUnit::tryFrom((string) $this->input('time_unit'));

        if (! $this->chargesTime() || ! is_numeric($this->input('time_value')) || $unit === null) {
            return null;
        }

        return $unit->toMinutes((float) $this->input('time_value'));
    }

    protected function chargesTime(): bool
    {
        return ActivityType::query()->whereKey($this->input('activity_type_id'))->where('charges_time_on_top', true)->exists();
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $needsCourt = (bool) ActivityType::query()
                ->whereKey($this->input('activity_type_id'))
                ->value('requires_court');

            if ($needsCourt && $this->route('matter')->court_id === null) {
                $validator->errors()->add('activity_type_id', 'This work is before a court and the matter has none yet. Set the court on the matter first.');
            }

            if ($this->chargesTime() && ! $this->filled('time_value')) {
                $validator->errors()->add('time_value', 'Give the time this took. It is charged on top.');
            }

            if ($this->chargesTime() && ($this->minutes() ?? 0) > 1440) {
                $validator->errors()->add('time_value', 'The time taken cannot be more than a day.');
            }
        }];
    }
}
