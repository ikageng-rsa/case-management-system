<?php

declare(strict_types=1);

namespace App\Http\Requests\Audit;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/*
 * The audit log and its export are filtered by who acted and what they did.
 * Both are reached from links, so a value that isn't a plain string is
 * ignored and the whole log is shown instead of failing.
 */
class FilterAuditLogRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /** The id of the person who acted; null for everyone. */
    public function actor(): ?string
    {
        return $this->filterValue('actor');
    }

    /** The event, such as `updated` or `exported`; null for every event. */
    public function action(): ?string
    {
        return $this->filterValue('action');
    }

    protected function filterValue(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
