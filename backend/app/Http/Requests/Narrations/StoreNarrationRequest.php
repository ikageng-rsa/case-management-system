<?php

declare(strict_types=1);

namespace App\Http\Requests\Narrations;

use App\Models\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNarrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The matter comes from the route and the author is the current user, so
     * neither is accepted here. Court is required only when the chosen activity
     * type is an appearance.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'activity_type_id' => ['required', 'integer', Rule::exists('activity_types', 'id')],
            'body' => ['required', 'string'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:now'],
            'court_id' => [
                $this->courtIsRequired() ? 'required' : 'nullable',
                'integer',
                Rule::exists('courts', 'id'),
            ],
        ];
    }

    /**
     * Whether the chosen activity type needs the court attended to be recorded.
     */
    protected function courtIsRequired(): bool
    {
        return (bool) ActivityType::query()
            ->whereKey($this->input('activity_type_id'))
            ->value('requires_court');
    }
}
