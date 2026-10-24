<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Auth\Permission;
use App\Enums\Client\ClientType;
use App\Enums\Client\ContactKind;
use App\Enums\Client\FicaDocument;
use App\Enums\Client\PopiaConsentMethod;
use App\Http\Requests\Client\Concerns\ValidatesIdentity;
use App\Models\Court;
use App\Models\MatterType;
use App\Rules\Client\UniqueClientRegistrationNumber;
use App\Rules\Client\ValidContactValue;
use App\Rules\Client\ValidRegistrationNumber;
use App\Rules\Document\AllowedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
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
        } + $this->contactRules() + $this->ficaRules() + $this->intakeRules();
    }

    /**
     * FICA documents can be filed at intake, one file for each the kind of client
     * is asked for. None is required: the rest can be added on the client later.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function ficaRules(): array
    {
        $type = $this->clientType();

        if ($type === null) {
            return [];
        }

        return collect(FicaDocument::forType($type))
            ->mapWithKeys(fn (FicaDocument $document) => [
                'fica.'.$document->value => ['nullable', 'file', 'max:'.config('documents.max_kilobytes'), new AllowedDocument],
            ])
            ->all();
    }

    /** @return array<string, UploadedFile> */
    public function ficaFiles(): array
    {
        $type = $this->clientType();

        return $type === null ? [] : collect(FicaDocument::forType($type))
            ->mapWithKeys(fn (FicaDocument $document) => [$document->value => $this->file('fica.'.$document->value)])
            ->filter()
            ->all();
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
     * The ways to reach the client that were given, keyed by contact kind.
     *
     * @return array<string, string>
     */
    public function contacts(): array
    {
        $fields = [
            'mobile' => ContactKind::Mobile,
            'email' => ContactKind::Email,
            'physical_address' => ContactKind::Physical,
        ];

        return collect($fields)
            ->filter(fn (ContactKind $kind, string $field) => $this->filled($field))
            ->mapWithKeys(fn (ContactKind $kind, string $field) => [$kind->value => $this->validated($field)])
            ->all();
    }

    /**
     * Consent is optional at intake and can be recorded on the client later. Given
     * now, it says how it was given, and a signed mandate has to be attached, as
     * when it is recorded on the client. A matter is opened, and so validated,
     * only when a matter type is chosen.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function intakeRules(): array
    {
        $rules = $this->user()->can(Permission::RecordPopiaConsent->value) && $this->boolean('popia_consent')
            ? [
                'popia_consent' => ['accepted'],
                'popia_method' => ['required', Rule::enum(PopiaConsentMethod::class)],
                'popia_mandate' => [
                    Rule::requiredIf($this->input('popia_method') === PopiaConsentMethod::Signed->value),
                    'nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:20480',
                ],
            ]
            : ['popia_consent' => ['nullable', 'boolean']];

        if (! $this->opensMatter()) {
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

    public function opensMatter(): bool
    {
        return $this->filled('matter_type_id');
    }

    /** The court the new matter is before; null when it isn't litigious or no matter is opened. */
    public function court(): ?Court
    {
        return $this->filled('court_id') ? Court::findOrFail($this->validated('court_id')) : null;
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
