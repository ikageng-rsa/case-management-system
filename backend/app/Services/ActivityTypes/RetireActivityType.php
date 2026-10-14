<?php

declare(strict_types=1);

namespace App\Services\ActivityTypes;

use App\Models\ActivityType;
use App\Services\Reference\CannotRemoveSystemRecord;

class RetireActivityType
{
    /** @return 'deleted'|'retired' */
    public function remove(ActivityType $type): string
    {
        if ($type->isSystem()) {
            throw new CannotRemoveSystemRecord("The {$type->name} kind of work");
        }

        if ($type->narrations()->exists()) {
            $type->delete();

            return 'retired';
        }

        $type->forceDelete();

        return 'deleted';
    }

    public function restore(ActivityType $type): void
    {
        $type->restore();
    }
}
