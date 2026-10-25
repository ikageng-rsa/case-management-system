<?php

declare(strict_types=1);

namespace App\Services\ActivityTypes;

use App\Enums\Narration\ActivityMeasure;
use App\Models\Reference\ActivityType;
use App\Services\Reference\ReferenceDataLocked;

class SaveActivityType
{
    /**
     * How a kind of work is counted decides what each narration of it is worth,
     * and that is worked out when it is read. So once a narration exists the
     * measure and increment stay as they are, and the firm adds a new kind
     * instead. The code is locked with them, and always for the system's own.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?ActivityType $type, array $data): ActivityType
    {
        $type ??= new ActivityType;

        if ($type->exists) {
            $locked = [];
            $used = $type->narrations()->exists();

            foreach (['code', 'increment'] as $field) {
                if (isset($data[$field]) && (string) $data[$field] !== (string) $type->{$field} && ($used || ($field === 'code' && $type->isSystem()))) {
                    $locked[] = $field;
                }
            }

            if (isset($data['measure']) && $data['measure'] !== $type->measure->value && $used) {
                $locked[] = 'measure';
            }

            if ($locked !== []) {
                throw new ReferenceDataLocked($locked, $type->isSystem() && $locked === ['code']
                    ? 'This code is used by the system itself, so it cannot change.'
                    : 'Narrations have been filed under this kind of work, so how it is counted can no longer change. Add a new kind instead.');
            }
        }

        $type->fill([
            'code' => $data['code'] ?? $type->code,
            'name' => trim($data['name']),
            'measure' => $type->exists && ! isset($data['measure']) ? $type->measure : ActivityMeasure::from($data['measure']),
            'increment' => $type->exists && ! isset($data['increment']) ? $type->increment : (int) $data['increment'],
            'default_billable' => (bool) ($data['default_billable'] ?? false),
            'requires_court' => (bool) ($data['requires_court'] ?? false),
        ])->save();

        return $type;
    }
}
