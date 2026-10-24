<?php

declare(strict_types=1);

namespace App\Http\Requests\Narration;

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
        ];
    }

    /**
     * An appearance is before a court, and the court is the matter's own: it is set
     * once on the matter, not repeated on every narration.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $needsCourt = (bool) ActivityType::query()
                ->whereKey($this->input('activity_type_id'))
                ->value('requires_court');

            if ($needsCourt && $this->route('matter')->court_id === null) {
                $validator->errors()->add('activity_type_id', 'This work is before a court and the matter has none yet. Set the court on the matter first.');
            }
        }];
    }
}
