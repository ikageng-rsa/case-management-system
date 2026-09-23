<?php

declare(strict_types=1);

namespace App\Services\Matters;

use App\Models\Matter;
use Carbon\CarbonInterface;

class CloseMatter
{
    public function close(Matter $matter, ?CarbonInterface $closedAt = null): Matter
    {
        if ($matter->isClosed()) {
            return $matter;
        }

        $matter->closed_at = $closedAt ?? now();
        $matter->save();

        return $matter;
    }
}
