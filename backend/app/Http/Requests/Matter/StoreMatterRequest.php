<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use App\Models\MatterType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatterRequest extends FormRequest
{
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
            'client_id' => ['required', 'uuid', Rule::exists('clients', 'id')->withoutTrashed()],
            'matter_type_id' => ['required', 'integer', Rule::exists('matter_types', 'id')->withoutTrashed()],
            'title' => ['nullable', 'string', 'max:255'],
            'instructed_at' => ['nullable', 'date'],
            // A litigious matter is before a court; a non-litigious one is not.
            'court_id' => [
                $this->courtIsRequired() ? 'required' : 'nullable',
                'integer',
                Rule::exists('courts', 'id'),
            ],
        ];
    }

    /**
     * Whether the chosen matter type is litigious, and so needs a court.
     */
    protected function courtIsRequired(): bool
    {
        return (bool) MatterType::query()
            ->whereKey($this->input('matter_type_id'))
            ->value('is_litigation');
    }
}
