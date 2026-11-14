<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Diary;

use Carbon\CarbonInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUserDiaryEntriesRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'show' => ['nullable', Rule::in(['pending', 'overdue', 'completed'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    /** Which entries: pending, overdue or completed. Pending unless one of the others. */
    public function show(): string
    {
        return $this->validated('show') ?? 'pending';
    }

    /** The first day due dates may fall on; null leaves the start open. */
    public function from(): ?CarbonInterface
    {
        return $this->date('from');
    }

    /** The last day due dates may fall on; null leaves the end open. */
    public function to(): ?CarbonInterface
    {
        return $this->date('to');
    }
}
