<?php

declare(strict_types=1);

namespace App\Http\Requests\Client;

use App\Enums\Client\FicaDocument;
use App\Models\Client\Client;
use App\Rules\Document\AllowedDocument;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFicaDocumentRequest extends FormRequest
{
    protected $errorBag = 'fica';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only the documents asked of this kind of client can be filed on it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'document' => ['required', Rule::enum(FicaDocument::class)->only(FicaDocument::forType($client->type))],
            'file' => ['required', 'file', 'max:'.config('documents.max_kilobytes'), new AllowedDocument],
        ];
    }
}
