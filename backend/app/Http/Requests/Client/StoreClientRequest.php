<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Auth\Permission;
use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Http\Requests\Client\Concerns\ValidatesIdentity;
use App\Models\MatterType;
use App\Rules\Client\UniqueClientRegistrationNumber;
use App\Rules\Client\ValidContactValue;
use App\Rules\Client\ValidRegistrationNumber;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientRequest extends FormRequest
{
    use ValidatesIdentity;

    protected $errorBag = 'client';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A client is either an individual with a SA ID or an entity with a registered
     * name and number the type decides which identity fields are required
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return match ($this->clientType()) {
            ClientType::Individual => $this->individualRules(),
            ClientType::Entity => $this->entityRules(),
            default => ['type' => ['required', Rule::enum(ClientType::class)]],
        } + $this->contactRules() + $this->intakeRules();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function individualRules(): array
    {
        return [
            'type' => ['required', Rule::enum(ClientType::class)],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
        ] + $this->identityRules();
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function ($validator): void {
            if ($this->clientType() === ClientType::Individual) {
                $this->checkBirthDateAgainstId($validator);
            }
        }];
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function entityRules(): array
    {
        return [
            'type' => ['required', Rule::enum(ClientType::class)],
            'entity_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['required', 'string', new ValidRegistrationNumber, new UniqueClientRegistrationNumber],
        ];
    }

    /**
     * Ways to reach the client, optional at intake. A new client has no
     * contacts yet, so there is nothing for them to clash with.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function contactRules(): array
    {
        return [
            'mobile' => ['nullable', 'string', new ValidContactValue(ContactKind::Mobile)],
            'email' => ['nullable', 'string', new ValidContactValue(ContactKind::Email)],
            'physical_address' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The client's consent is recorded by whoever is cleared to record it, and
     * they have to confirm it before the client is registered. The matter is
     * only validated when the user is going on to open one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function intakeRules(): array
    {
        $rules = [
            'intent' => ['nullable', Rule::in(['client', 'matter'])],
            'popia_consent' => [$this->user()->can(Permission::RecordPopiaConsent->value) ? 'accepted' : 'nullable'],
        ];

        if ($this->input('intent') !== 'matter') {
            return $rules;
        }

        return $rules + [
            'matter_type_id' => ['required', 'integer', Rule::exists('matter_types', 'id')->withoutTrashed()],
            // A litigious matter is before a court; a non-litigious one is not.
            'court_id' => [
                $this->courtIsRequired() ? 'required' : 'nullable',
                'integer',
                Rule::exists('courts', 'id')->withoutTrashed(),
            ],
        ];
    }

    protected function courtIsRequired(): bool
    {
        return (bool) MatterType::query()
            ->whereKey($this->input('matter_type_id'))
            ->value('is_litigation');
    }

    protected function clientType(): ?ClientType
    {
        return ClientType::tryFrom((string) $this->input('type'));
    }
}
