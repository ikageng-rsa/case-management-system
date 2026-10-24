<?php

declare(strict_types=1);

namespace App\Services\Courts;

use App\Models\Reference\Court;

class RetireCourt
{
    /**
     * A court nothing refers to is removed outright. One that a matter
     * carries is retired: no longer offered, still read correctly.
     *
     * @return 'deleted'|'retired'
     */
    public function remove(Court $court): string
    {
        $inUse = $court->matters()->withTrashed()->exists();

        if ($inUse) {
            $court->delete();

            return 'retired';
        }

        $court->forceDelete();

        return 'deleted';
    }

    public function restore(Court $court): void
    {
        $court->restore();
    }
}
