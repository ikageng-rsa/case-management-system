<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 * One value is changed at a time, in place, so every field is optional and only
 * what was sent is touched. The matter's type, client and reference are not here:
 * they are what the file is, not details of it.
 */
class UpdateMatterRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'instructed_at' => ['sometimes', 'nullable', 'date'],
            'court_id' => ['sometimes', 'nullable', 'integer', Rule::exists('courts', 'id')->withoutTrashed()],
            'prescribes_at' => ['sometimes', 'nullable', 'date'],
            // The date a claim is lost on is not changed without saying why.
            'reason' => ['required_with:prescribes_at', 'nullable', 'string', 'max:500'],
        ];
    }
}
