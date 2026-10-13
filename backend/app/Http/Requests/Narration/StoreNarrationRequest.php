<?php

declare(strict_types=1);

namespace App\Http\Requests\Narration;

use App\Models\ActivityType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'activity_type_id' => ['required', 'integer', Rule::exists('activity_types', 'id')],
            'body' => ['required', 'string', 'max:5000'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            // An appearance is before a court, and the court affects the tariff.
            'court_id' => [
                $this->courtIsRequired() ? 'required' : 'nullable',
                'integer',
                Rule::exists('courts', 'id'),
            ],
        ];
    }

    protected function courtIsRequired(): bool
    {
        return (bool) ActivityType::query()
            ->whereKey($this->input('activity_type_id'))
            ->value('requires_court');
    }
}
