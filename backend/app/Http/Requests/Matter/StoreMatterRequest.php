<?php

declare(strict_types=1);

namespace App\Http\Requests\Matter;

use App\Enums\Auth\Permission;
use App\Enums\Matter\PartyRole;
use App\Models\Court;
use App\Models\MatterType;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMatterRequest extends FormRequest
{
    protected $errorBag = 'matter';

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
                Rule::exists('courts', 'id')->withoutTrashed(),
            ],
            // Choosing who carries the file is assigning it, which has its own permission.
            'responsible_attorney_id' => $this->user()->can(Permission::AssignMatters->value)
                ? ['nullable', Rule::exists('users', 'id')]
                : ['prohibited'],
            // Becomes the first entry in the ledger.
            'narration' => ['nullable', 'string', 'max:5000'],
            // Everyone else in the matter, besides the client.
            'parties' => ['nullable', 'array', 'max:20'],
            'parties.*.name' => ['required', 'string', 'max:255'],
            'parties.*.role' => ['required', Rule::enum(PartyRole::class)],
            'parties.*.representative' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** The court the matter is before; null for a non-litigious matter. */
    public function court(): ?Court
    {
        return $this->filled('court_id') ? Court::findOrFail($this->validated('court_id')) : null;
    }

    /** Who carries the file from the start, if anyone was chosen. */
    public function responsibleAttorney(): ?User
    {
        return $this->filled('responsible_attorney_id') ? User::findOrFail($this->validated('responsible_attorney_id')) : null;
    }

    /** The first ledger entry, if one was written. */
    public function openingNarration(): ?string
    {
        return $this->filled('narration') ? $this->validated('narration') : null;
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
