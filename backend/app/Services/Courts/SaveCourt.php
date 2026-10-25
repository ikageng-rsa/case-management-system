<?php

declare(strict_types=1);

namespace App\Services\Courts;

use App\Enums\Court\CourtTier;
use App\Models\Reference\Court;

class SaveCourt
{
    /**
     * Nothing about a court is locked: it is cited on a file as its name and seat
     * stand, so correcting a spelling corrects it everywhere, which is the intent.
     *
     * @param  array{tier: string, name: string, seat: string}  $data
     */
    public function save(?Court $court, array $data): Court
    {
        $court ??= new Court;

        $court->fill([
            'tier' => CourtTier::from($data['tier']),
            'name' => trim($data['name']),
            'seat' => trim($data['seat']),
        ])->save();

        return $court;
    }
}
