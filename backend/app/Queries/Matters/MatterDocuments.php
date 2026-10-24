<?php

declare(strict_types=1);

namespace App\Queries\Matters;

use App\Enums\Document\DocumentKind;
use App\Models\Matter\Matter;

/*
 * The documents on a matter's documents tab, newest first, narrowed to one
 * kind when one is chosen, with how many of each kind there are.
 */
class MatterDocuments
{
    /** @return array<string, mixed> */
    public function for(Matter $matter, ?DocumentKind $kind): array
    {
        $all = $matter->documents();

        return [
            'documents' => $all
                ->when($kind, fn ($documents) => $documents->filter(
                    fn ($media) => $media->getCustomProperty('kind') === $kind->value,
                ))
                ->sortByDesc('created_at')
                ->values(),
            'allDocumentsCount' => $all->count(),
            'kind' => $kind,
            'kindCounts' => $all->countBy(fn ($media) => $media->getCustomProperty('kind')),
            'kinds' => DocumentKind::cases(),
        ];
    }
}
