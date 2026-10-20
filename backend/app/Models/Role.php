<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Spatie\Permission\Models\Role as BaseRole;

/*
 * A role of the firm. The package's own model, but one that records itself in
 * the activity log, so a role being created, renamed or removed leaves a trace.
 * What a role may do is recorded where the permissions are changed, by
 * SaveRole, since the package reports a change of permissions as revoking
 * everything and granting it again.
 */
#[Fillable(['name', 'guard_name'])]
class Role extends BaseRole
{
    use RecordsActivity;

    public function auditLabel(): string
    {
        return 'role '.$this->name;
    }
}
