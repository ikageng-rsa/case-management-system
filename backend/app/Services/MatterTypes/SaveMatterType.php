<?php

declare(strict_types=1);

namespace App\Services\MatterTypes;

use App\Models\MatterType;
use App\Services\Reference\ReferenceDataLocked;

class SaveMatterType
{
    /**
     * The code is printed into every reference the type has issued, so once a
     * matter exists the code stays as it is. Everything else may change, and
     * applies to matters opened from then on.
     *
     * @param  array{code?: string, name: string, default_prescription_months?: ?int, is_litigation?: bool}  $data
     */
    public function save(?MatterType $type, array $data): MatterType
    {
        $type ??= new MatterType;

        if ($type->exists && isset($data['code']) && $data['code'] !== $type->code && $type->matters()->withTrashed()->exists()) {
            throw new ReferenceDataLocked(['code'], "The code {$type->code} is on the reference of every matter of this type, so it can no longer change.");
        }

        $type->fill([
            'code' => $type->exists ? ($data['code'] ?? $type->code) : $data['code'],
            'name' => trim($data['name']),
            'default_prescription_months' => $data['default_prescription_months'] ?? null,
            'is_litigation' => (bool) ($data['is_litigation'] ?? false),
        ])->save();

        return $type;
    }
}
