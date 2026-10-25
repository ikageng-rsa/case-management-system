<?php

declare(strict_types=1);

namespace App\Services\MatterTypes;

use App\Models\Reference\MatterType;

class RetireMatterType
{
    /** @return 'deleted'|'retired' */
    public function remove(MatterType $type): string
    {
        if ($type->matters()->withTrashed()->exists()) {
            $type->delete();

            return 'retired';
        }

        $type->forceDelete();

        return 'deleted';
    }

    public function restore(MatterType $type): void
    {
        $type->restore();
    }
}
