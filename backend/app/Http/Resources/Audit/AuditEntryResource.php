<?php

declare(strict_types=1);

namespace App\Http\Resources\Audit;

use App\Http\Resources\ApiResource;
use App\Http\Resources\User\UserResource;
use App\Models\Audit\Activity;
use Illuminate\Http\Request;

/**
 * One line of the audit log: who did what to which record, and what it changed
 * from and to. The log never holds a revealed value, so none can leave here.
 *
 * @mixin Activity
 */
class AuditEntryResource extends ApiResource
{
    public function toType(Request $request): string
    {
        return 'audit-entries';
    }

    /** @return array<string, mixed> */
    public function toAttributes(Request $request): array
    {
        return [
            'event' => $this->event,
            'description' => $this->description,
            'subject_type' => $this->subject_type,
            'subject_id' => $this->subject_id === null ? null : (string) $this->subject_id,
            'changes' => (object) $this->changes(),
            'field' => $this->getProperty('field'),
            'ip' => $this->getProperty('ip'),
            'via' => $this->getProperty('via', 'web'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, class-string> */
    public function toRelationships(Request $request): array
    {
        return [
            'causer' => UserResource::class,
        ];
    }

    /** @return array<string, array{old: mixed, new: mixed}> */
    private function changes(): array
    {
        $new = $this->attribute_changes?->get('attributes', []) ?? [];
        $old = $this->attribute_changes?->get('old', []) ?? [];

        return collect($new)
            ->map(fn (mixed $value, string $field) => ['old' => $old[$field] ?? null, 'new' => $value])
            ->all();
    }
}
